<script>
    $(function () {
        var boxes = $('input[name="permissions[]"]');

        boxes.on('change', function () {
            var section = $(this).data('section');
            var ability = $(this).data('ability');

            if (ability === 'manage' && this.checked) {
                $('input[data-section="' + section + '"][data-ability="view"]').prop('checked', true);
            }

            if (ability === 'view' && !this.checked) {
                $('input[data-section="' + section + '"][data-ability="manage"]').prop('checked', false);
            }
        });

        $('[data-select-all]').on('click', function (event) {
            event.preventDefault();
            boxes.prop('checked', $(this).data('select-all') === 'all');
        });
    });
</script>
