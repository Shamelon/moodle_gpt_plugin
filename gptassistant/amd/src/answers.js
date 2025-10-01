define(['jquery'], function($) {
    return {
        init: function() {
            $('#answers_add_fields').on('click', function() {
                var lastAnswer = $('[name^="answer"]').last();
                lastAnswer.after('<div class="form-group"><input type="text" name="answer[]" class="form-control"></div>');
            });
        }
    };
});