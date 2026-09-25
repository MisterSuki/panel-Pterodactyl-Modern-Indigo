import http from '@/api/http';

export interface RegisterData {
    nameFirst: string;
    nameLast: string;
    username: string;
    email: string;
    password: string;
    passwordConfirmation: string;
    recaptchaData?: string | null;
}

export interface RegisterResponse {
    complete: boolean;
    intended?: string;
}

export default (data: RegisterData): Promise<RegisterResponse> => {
    return new Promise((resolve, reject) => {
        http.get('/sanctum/csrf-cookie')
            .then(() =>
                http.post('/auth/register', {
                    name_first: data.nameFirst,
                    name_last: data.nameLast,
                    username: data.username,
                    email: data.email,
                    password: data.password,
                    // eslint-disable-next-line camelcase
                    password_confirmation: data.passwordConfirmation,
                    'g-recaptcha-response': data.recaptchaData,
                })
            )
            .then((response) => {
                if (!(response.data instanceof Object)) {
                    return reject(new Error('An error occurred while processing the registration request.'));
                }

                return resolve({
                    complete: response.data.data.complete,
                    intended: response.data.data.intended || undefined,
                });
            })
            .catch(reject);
    });
};
