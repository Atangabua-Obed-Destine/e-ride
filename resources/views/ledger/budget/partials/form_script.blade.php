<script>
    // Show the department (location) only for a departmental budget, and require it there.
    $(function () {
        function sync() {
            var dept = $('#type').val() === 'departmental';
            $('#department_div').toggle(dept);
            $('#location_id').prop('required', dept);
            $('#dept_required').toggle(dept);
        }
        $('#type').on('change', sync);
        sync();
    });
</script>
