/*
 * The look of the panel: the accent colour and the background behind the pages.
 *
 * The page gives window.PdAppearanceConfig = { site, user }: "site" is what the administration chose (and whether people
 * may pick their own background), "user" what the person signed in chose for themselves (null for visitors).
 *
 * The accent colour is a set of CSS variables (--pd-accent-50 ... --pd-accent-900, as "r g b"), read by the styles of the
 * dashboard and of the administration. The background is one fixed layer under the page: a gradient, a picture, or an
 * animated effect drawn by a small WebGL shader. The effect is drawn at half the size of the screen and at most 30 times
 * a second, stops while the tab is hidden, and stands still for people who asked for less motion.
 */
(function () {
    'use strict';

    var ACCENTS = {
        blue: '#0a84ff',
        indigo: '#5e5ce6',
        purple: '#bf5af2',
        pink: '#ff375f',
        red: '#ff453a',
        orange: '#ff9f0a',
        green: '#30d158',
        teal: '#30b0c7',
        graphite: '#8e8e93',
    };

    var GRADIENTS = {
        liquid: null, // the wallpaper the stylesheets already paint
        midnight:
            'radial-gradient(60% 50% at 50% 0%, rgba(var(--pd-a), 0.22), transparent 70%), linear-gradient(180deg, #07080f 0%, #030308 100%)',
        ocean:
            'radial-gradient(55% 45% at 15% 10%, rgba(100, 210, 255, 0.45), transparent 70%), radial-gradient(50% 50% at 85% 90%, rgba(10, 132, 255, 0.45), transparent 70%), linear-gradient(160deg, #04203a 0%, #061a33 50%, #020814 100%)',
        sunset:
            'radial-gradient(55% 45% at 85% 10%, rgba(255, 159, 10, 0.45), transparent 70%), radial-gradient(55% 50% at 10% 90%, rgba(255, 55, 95, 0.45), transparent 70%), linear-gradient(160deg, #2a0c24 0%, #1d0a1c 50%, #0b0509 100%)',
        forest:
            'radial-gradient(55% 45% at 15% 10%, rgba(48, 209, 88, 0.35), transparent 70%), radial-gradient(50% 50% at 85% 85%, rgba(48, 176, 199, 0.35), transparent 70%), linear-gradient(160deg, #06210f 0%, #071a14 50%, #030a07 100%)',
    };

    var EFFECTS = ['aurora', 'silk', 'plasma', 'waves', 'lines', 'glow', 'beams', 'stars', 'bubbles'];

    var config = window.PdAppearanceConfig || {};
    var site = config.site || {};
    var user = config.user || null;
    var root = document.documentElement;

    // ---- Colours -------------------------------------------------------------------------------------------------

    function hexToRgb(hex) {
        var n = parseInt(hex.slice(1), 16);

        return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
    }

    function mix(rgb, target, amount) {
        return rgb.map(function (c, i) {
            return Math.round(c + (target[i] - c) * amount);
        });
    }

    function rotateHue(rgb, degrees) {
        var r = rgb[0] / 255,
            g = rgb[1] / 255,
            b = rgb[2] / 255;
        var max = Math.max(r, g, b),
            min = Math.min(r, g, b);
        var h = 0,
            s = 0,
            l = (max + min) / 2;
        if (max !== min) {
            var d = max - min;
            s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
            if (max === r) h = (g - b) / d + (g < b ? 6 : 0);
            else if (max === g) h = (b - r) / d + 2;
            else h = (r - g) / d + 4;
            h /= 6;
        }
        h = (h + degrees / 360 + 1) % 1;
        function f(p, q, t) {
            if (t < 0) t += 1;
            if (t > 1) t -= 1;
            if (t < 1 / 6) return p + (q - p) * 6 * t;
            if (t < 1 / 2) return q;
            if (t < 2 / 3) return p + (q - p) * (2 / 3 - t) * 6;

            return p;
        }
        if (s === 0) return [l * 255, l * 255, l * 255].map(Math.round);
        var q = l < 0.5 ? l * (1 + s) : l + s - l * s;
        var p = 2 * l - q;

        return [f(p, q, h + 1 / 3), f(p, q, h), f(p, q, h - 1 / 3)].map(function (v) {
            return Math.round(v * 255);
        });
    }

    var SHADES = { 50: 0.9, 100: 0.8, 200: 0.6, 300: 0.4, 400: 0.2, 500: 0, 600: -0.12, 700: -0.26, 800: -0.42, 900: -0.58 };

    function applyAccent(name) {
        var base = hexToRgb(ACCENTS[name] || ACCENTS.blue);
        Object.keys(SHADES).forEach(function (shade) {
            var amount = SHADES[shade];
            var rgb = amount >= 0 ? mix(base, [255, 255, 255], amount) : mix(base, [0, 0, 0], -amount);
            root.style.setProperty('--pd-accent-' + shade, rgb.join(' '));
        });
        root.style.setProperty('--pd-a', base.join(', '));
        root.style.setProperty('--pd-accent', ACCENTS[name] || ACCENTS.blue);
    }

    // The three colours the effects are painted with: the accent, and two neighbours on the colour wheel.
    function palette(name) {
        var base = hexToRgb(ACCENTS[name] || ACCENTS.blue);
        if (name === 'graphite') {
            return [[142, 142, 147], [99, 99, 102], [174, 174, 178]];
        }

        return [base, rotateHue(base, 48), rotateHue(base, -42)];
    }

    // ---- Effects (WebGL) -------------------------------------------------------------------------------------------

    var COMMON = [
        'precision mediump float;',
        'uniform vec2 r; uniform float t; uniform vec3 c1; uniform vec3 c2; uniform vec3 c3;',
        'float hash(vec2 p){ p = fract(p * vec2(123.34, 456.21)); p += dot(p, p + 45.32); return fract(p.x * p.y); }',
        'float noise(vec2 p){ vec2 i = floor(p); vec2 f = fract(p); f = f * f * (3.0 - 2.0 * f);',
        '  return mix(mix(hash(i), hash(i + vec2(1.0, 0.0)), f.x), mix(hash(i + vec2(0.0, 1.0)), hash(i + vec2(1.0, 1.0)), f.x), f.y); }',
        'float fbm(vec2 p){ float v = 0.0; float a = 0.5; for (int i = 0; i < 4; i++) { v += a * noise(p); p *= 2.02; a *= 0.5; } return v; }',
        'vec3 bg(vec2 uv){ return mix(vec3(0.016, 0.02, 0.05), vec3(0.035, 0.028, 0.08), uv.y); }',
    ].join('\n');

    var SHADERS = {
        aurora: [
            'void main(){ vec2 uv = gl_FragCoord.xy / r; float asp = r.x / r.y;',
            '  float x = uv.x * asp;',
            '  float curtain = fbm(vec2(x * 1.4 + t * 0.04, t * 0.06));',
            '  float streak = fbm(vec2(x * 9.0 + curtain * 4.0, t * 0.12));',
            '  float top = 0.58 + curtain * 0.32;',
            '  float body = smoothstep(top - 0.5, top, uv.y) * (1.0 - smoothstep(top, top + 0.22, uv.y));',
            '  float glow = body * (0.3 + 0.7 * streak);',
            '  vec3 col = mix(c1, c2, smoothstep(0.25, 0.75, curtain));',
            '  col = mix(col, c3, smoothstep(top - 0.15, top + 0.15, uv.y) * 0.7);',
            '  gl_FragColor = vec4(bg(uv) + col * glow * 0.85, 1.0); }',
        ],
        silk: [
            'void main(){ vec2 uv = gl_FragCoord.xy / r; vec2 p = uv * vec2(r.x / r.y, 1.0) * 2.6; float k = t * 0.22;',
            '  p += vec2(sin(p.y * 0.9 + k) * 0.5, cos(p.x * 0.7 - k) * 0.35);',
            '  float w = sin(p.x * 1.5 + p.y * 2.2 + sin(p.y * 1.4 - k * 1.6) * 1.6 + k * 2.0);',
            '  float fold = 0.5 + 0.5 * w; float shine = pow(fold, 6.0);',
            '  float grain = (hash(gl_FragCoord.xy + fract(t)) - 0.5) * 0.035;',
            '  vec3 col = mix(bg(uv), c1 * 0.5, fold * 0.9) + mix(c3, vec3(1.0), 0.3) * shine * 0.28 + grain;',
            '  gl_FragColor = vec4(col, 1.0); }',
        ],
        plasma: [
            'void main(){ vec2 uv = gl_FragCoord.xy / r; vec2 p = (gl_FragCoord.xy - 0.5 * r) / r.y; float k = t * 0.18;',
            '  float v = sin(p.x * 3.0 + k * 2.0) + sin(p.y * 2.6 - k * 1.5) + sin((p.x + p.y) * 2.2 + k) + sin(length(p * 3.2) - k * 1.3);',
            '  v *= 0.25;',
            '  vec3 col = mix(c1, c2, 0.5 + 0.5 * sin(v * 3.14 + k));',
            '  col = mix(col, c3, 0.5 + 0.5 * cos(v * 2.0 - k));',
            '  float m = smoothstep(-0.35, 0.95, v);',
            '  gl_FragColor = vec4(bg(uv) + col * m * 0.75, 1.0); }',
        ],
        waves: [
            'void main(){ vec2 uv = gl_FragCoord.xy / r; vec3 col = bg(uv);',
            '  for (int i = 0; i < 5; i++) { float f = float(i);',
            '    float y = 0.18 + f * 0.13 + 0.06 * sin(uv.x * (2.0 + f * 0.5) + t * (0.22 + f * 0.05) + f * 1.7);',
            '    float band = smoothstep(y + 0.16, y - 0.02, uv.y);',
            '    vec3 c = mix(c1, c2, f / 4.0);',
            '    col = mix(col, c * (0.55 - f * 0.05), band * 0.42);',
            '    col += c * exp(-abs(uv.y - y) * 90.0) * 0.22; }',
            '  gl_FragColor = vec4(col, 1.0); }',
        ],
        lines: [
            'void main(){ vec2 uv = gl_FragCoord.xy / r; vec3 col = bg(uv); float px = 1.0 / r.y;',
            '  for (int i = 0; i < 16; i++) { float f = float(i) / 15.0;',
            '    float y = 0.5 + (f - 0.5) * 0.55 + 0.14 * sin(uv.x * 2.8 + t * 0.32 + f * 2.4) * sin(uv.x * 1.2 - t * 0.17 + f);',
            '    float d = abs(uv.y - y);',
            '    vec3 c = mix(c1, c2, f);',
            '    col += c * (smoothstep(px * 2.2, 0.0, d) * 0.55 + exp(-d * 70.0) * 0.05); }',
            '  gl_FragColor = vec4(col, 1.0); }',
        ],
        glow: [
            'void main(){ vec2 uv = gl_FragCoord.xy / r;',
            '  float y = 0.5 + 0.05 * sin(uv.x * 6.0 + t * 0.7) + 0.03 * sin(uv.x * 13.0 - t * 1.0);',
            '  float d = abs(uv.y - y);',
            '  vec3 c = mix(mix(c3, c1, smoothstep(0.0, 0.5, uv.x)), c2, smoothstep(0.5, 1.0, uv.x));',
            '  vec3 col = bg(uv) + c * (exp(-d * 110.0) * 1.1 + exp(-d * 14.0) * 0.22 + exp(-d * 3.0) * 0.06);',
            '  gl_FragColor = vec4(col, 1.0); }',
        ],
        beams: [
            'void main(){ vec2 uv = gl_FragCoord.xy / r; vec3 col = bg(uv);',
            '  for (int i = 0; i < 8; i++) { float f = float(i);',
            '    float x = fract(0.07 + f * 0.131 + 0.025 * sin(t * 0.25 + f * 1.3));',
            '    float w = 0.012 + 0.03 * hash(vec2(f, 2.0));',
            '    float beam = exp(-pow((uv.x - x) / w, 2.0));',
            '    float flick = 0.55 + 0.45 * sin(t * (0.4 + 0.25 * f) + f * 3.0);',
            '    col += mix(c1, c2, fract(f * 0.37)) * beam * smoothstep(0.0, 1.0, uv.y) * flick * 0.45; }',
            '  col += c3 * exp(-(1.0 - uv.y) * 6.0) * 0.08;',
            '  gl_FragColor = vec4(col, 1.0); }',
        ],
        stars: [
            'void main(){ vec2 uv = gl_FragCoord.xy / r; vec3 col = bg(uv);',
            '  float neb = fbm(uv * vec2(r.x / r.y, 1.0) * 2.2 + vec2(t * 0.015, t * 0.01));',
            '  col += mix(c1, c2, neb) * pow(neb, 2.6) * 0.75;',
            '  vec2 g = gl_FragCoord.xy / r.y * 36.0; vec2 id = floor(g); vec2 f = fract(g) - 0.5; float h = hash(id);',
            '  if (h > 0.9) { vec2 o = vec2(hash(id + 1.3), hash(id + 7.1)) - 0.5; float d = length(f - o * 0.6);',
            '    float tw = 0.45 + 0.55 * sin(t * (0.8 + h * 2.5) + h * 40.0);',
            '    col += mix(vec3(1.0), c3, 0.3) * smoothstep(0.1, 0.0, d) * tw; }',
            '  gl_FragColor = vec4(col, 1.0); }',
        ],
        bubbles: [
            'void main(){ vec2 uv = gl_FragCoord.xy / r; vec2 p = (gl_FragCoord.xy - 0.5 * r) / r.y; vec3 col = bg(uv);',
            '  for (int i = 0; i < 6; i++) { float f = float(i);',
            '    vec2 c = vec2(sin(t * 0.11 * (1.0 + f * 0.3) + f * 1.7) * 0.75, cos(t * 0.09 * (1.0 + f * 0.25) + f * 2.3) * 0.4);',
            '    float d = length(p - c);',
            '    vec3 k = mod(f, 3.0) < 1.0 ? c1 : (mod(f, 3.0) < 2.0 ? c2 : c3);',
            '    col += k * exp(-d * d * 8.0) * 0.2; }',
            '  gl_FragColor = vec4(col, 1.0); }',
        ],
    };

    var VERTEX = 'attribute vec2 p; void main(){ gl_Position = vec4(p, 0.0, 1.0); }';

    // A small WebGL painter for one canvas, with one program per effect, built when first needed.
    function Painter(canvas) {
        var gl =
            canvas.getContext('webgl', { antialias: false, alpha: false, depth: false, preserveDrawingBuffer: true }) ||
            canvas.getContext('experimental-webgl');
        if (!gl) {
            this.gl = null;

            return;
        }
        this.gl = gl;
        this.programs = {};
        var buffer = gl.createBuffer();
        gl.bindBuffer(gl.ARRAY_BUFFER, buffer);
        gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 3, -1, -1, 3]), gl.STATIC_DRAW);
    }

    Painter.prototype.program = function (effect) {
        var gl = this.gl;
        if (this.programs[effect] !== undefined) return this.programs[effect];
        function compile(type, source) {
            var shader = gl.createShader(type);
            gl.shaderSource(shader, source);
            gl.compileShader(shader);
            if (!gl.getShaderParameter(shader, gl.COMPILE_STATUS)) {
                if (window.console) console.warn('appearance: ' + gl.getShaderInfoLog(shader));

                return null;
            }

            return shader;
        }
        var vs = compile(gl.VERTEX_SHADER, VERTEX);
        var fs = compile(gl.FRAGMENT_SHADER, COMMON + '\n' + SHADERS[effect].join('\n'));
        if (!vs || !fs) return (this.programs[effect] = null);
        var program = gl.createProgram();
        gl.attachShader(program, vs);
        gl.attachShader(program, fs);
        gl.linkProgram(program);
        if (!gl.getProgramParameter(program, gl.LINK_STATUS)) return (this.programs[effect] = null);
        program.u = {
            r: gl.getUniformLocation(program, 'r'),
            t: gl.getUniformLocation(program, 't'),
            c1: gl.getUniformLocation(program, 'c1'),
            c2: gl.getUniformLocation(program, 'c2'),
            c3: gl.getUniformLocation(program, 'c3'),
        };
        program.a = gl.getAttribLocation(program, 'p');

        return (this.programs[effect] = program);
    };

    Painter.prototype.draw = function (effect, colors, time) {
        var gl = this.gl;
        var program = gl && SHADERS[effect] ? this.program(effect) : null;
        if (!program) return false;
        var w = gl.canvas.width,
            h = gl.canvas.height;
        gl.viewport(0, 0, w, h);
        gl.useProgram(program);
        gl.enableVertexAttribArray(program.a);
        gl.vertexAttribPointer(program.a, 2, gl.FLOAT, false, 0, 0);
        gl.uniform2f(program.u.r, w, h);
        gl.uniform1f(program.u.t, time);
        ['c1', 'c2', 'c3'].forEach(function (name, i) {
            gl.uniform3f(program.u[name], colors[i][0] / 255, colors[i][1] / 255, colors[i][2] / 255);
        });
        gl.drawArrays(gl.TRIANGLES, 0, 3);

        return true;
    };

    // ---- The background layer -------------------------------------------------------------------------------------

    var layer = null;
    var running = null;

    function stop() {
        if (running) {
            running.stopped = true;
            document.removeEventListener('visibilitychange', running.onVisibility);
            window.removeEventListener('resize', running.onResize);
            running = null;
        }
    }

    function reducedMotion(prefs) {
        if (prefs && prefs.motion === false) return true;
        try {
            return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        } catch (e) {
            return false;
        }
    }

    // What is shown, from the choice of the administration and the one of the person.
    function resolve(prefs, siteValues) {
        var s = siteValues || site;
        var p = prefs || {};
        var accent = p.accent && p.accent !== 'default' && ACCENTS[p.accent] ? p.accent : s.accent || 'blue';
        var mode = s.mode || 'gradient',
            gradient = s.gradient || 'liquid',
            effect = s.effect || 'aurora';
        if (s.userChoice !== false && p.mode && p.mode !== 'default' && (p.mode !== 'image' || s.image)) {
            mode = p.mode;
            gradient = p.gradient || gradient;
            effect = p.effect || effect;
        }
        if (mode === 'image' && !s.image) mode = 'gradient';

        return {
            accent: accent,
            mode: mode,
            gradient: GRADIENTS.hasOwnProperty(gradient) ? gradient : 'liquid',
            effect: EFFECTS.indexOf(effect) === -1 ? 'aurora' : effect,
            image: s.image || null,
            dim: Math.max(0, Math.min(80, Number(s.dim) || 0)),
            blur: Math.max(0, Math.min(30, Number(s.blur) || 0)),
            still: reducedMotion(p),
        };
    }

    function makeLayer() {
        var el = document.createElement('div');
        el.id = 'pd-background';
        el.setAttribute('aria-hidden', 'true');
        el.style.cssText = 'position:fixed;inset:0;z-index:-1;pointer-events:none;overflow:hidden;';
        document.body.insertBefore(el, document.body.firstChild);

        return el;
    }

    function draw(look) {
        stop();
        if (!document.body) return;
        if (!layer) layer = makeLayer();
        layer.innerHTML = '';
        root.setAttribute('data-pd-background', look.mode === 'gradient' ? look.gradient : look.mode);

        if (look.mode === 'gradient') {
            layer.style.display = GRADIENTS[look.gradient] ? 'block' : 'none';
            layer.style.background = GRADIENTS[look.gradient] || '';

            return;
        }
        layer.style.display = 'block';
        layer.style.background = '#05060f';

        if (look.mode === 'image') {
            var picture = document.createElement('div');
            picture.style.cssText =
                'position:absolute;inset:0;background-position:center;background-size:cover;background-repeat:no-repeat;';
            picture.style.backgroundImage = 'url("' + String(look.image).replace(/"/g, '%22') + '")';
            if (look.blur > 0) {
                picture.style.filter = 'blur(' + look.blur + 'px)';
                picture.style.transform = 'scale(' + (1 + look.blur / 120 + 0.02) + ')';
            }
            layer.appendChild(picture);
        } else {
            var canvas = document.createElement('canvas');
            canvas.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;display:block;';
            layer.appendChild(canvas);
            var painter = new Painter(canvas);
            if (!painter.gl) {
                layer.style.background = GRADIENTS.midnight;
                canvas.remove();
            } else {
                animate(painter, canvas, look);
            }
        }

        if (look.dim > 0) {
            var veil = document.createElement('div');
            veil.style.cssText = 'position:absolute;inset:0;background:rgba(0,0,0,' + look.dim / 100 + ');';
            layer.appendChild(veil);
        }
    }

    function animate(painter, canvas, look) {
        var colors = palette(look.accent);
        var state = { stopped: false };
        var start = performance.now() - 20000;
        var last = 0;

        function size() {
            var scale = 0.5;
            canvas.width = Math.max(64, Math.round(window.innerWidth * scale));
            canvas.height = Math.max(64, Math.round(window.innerHeight * scale));
        }

        function frame(now) {
            if (state.stopped) return;
            if (!document.hidden && now - last >= 33) {
                last = now;
                painter.draw(look.effect, colors, (now - start) / 1000);
            }
            if (!look.still) requestAnimationFrame(frame);
        }

        state.onResize = function () {
            size();
            painter.draw(look.effect, colors, (performance.now() - start) / 1000);
        };
        state.onVisibility = function () {
            last = 0;
        };
        window.addEventListener('resize', state.onResize);
        document.addEventListener('visibilitychange', state.onVisibility);
        running = state;
        size();
        if (look.still) {
            painter.draw(look.effect, colors, 24);
        } else {
            requestAnimationFrame(frame);
        }
    }

    // ---- Small pictures of the effects, for the pages where they are chosen ----------------------------------------

    var thumbCanvas = null,
        thumbPainter = null,
        thumbCache = {};

    function thumbnail(effect, accent, width, height) {
        var key = effect + ':' + accent + ':' + width + 'x' + height;
        if (thumbCache[key]) return thumbCache[key];
        if (!thumbCanvas) {
            thumbCanvas = document.createElement('canvas');
            thumbPainter = new Painter(thumbCanvas);
        }
        if (!thumbPainter.gl) return null;
        thumbCanvas.width = width;
        thumbCanvas.height = height;
        if (!thumbPainter.draw(effect, palette(accent), 31)) return null;

        return (thumbCache[key] = thumbCanvas.toDataURL('image/jpeg', 0.85));
    }

    // ---- Public ---------------------------------------------------------------------------------------------------

    var current = null;

    function apply(prefs, siteValues) {
        var look = resolve(prefs, siteValues);
        applyAccent(look.accent);
        var signature = JSON.stringify(look);
        if (signature === current) return look;
        current = signature;
        if (document.body) {
            draw(look);
        } else {
            document.addEventListener('DOMContentLoaded', function () {
                draw(look);
            });
        }

        return look;
    }

    window.PdAppearance = {
        accents: ACCENTS,
        effects: EFFECTS,
        gradients: Object.keys(GRADIENTS),
        gradientCss: function (name) {
            return GRADIENTS[name] || null;
        },
        site: site,
        user: user,
        // Shows a choice right away (used while it is being picked), without keeping it.
        preview: function (prefs, siteValues) {
            return apply(prefs, siteValues);
        },
        // Shows a choice that was saved: it becomes the person's one for the rest of the visit.
        set: function (prefs) {
            user = prefs;
            window.PdAppearance.user = prefs;

            return apply(prefs);
        },
        resolve: resolve,
        thumbnail: thumbnail,
    };

    apply(user);
})();
