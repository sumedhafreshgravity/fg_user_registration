
(function($) {
    $('#edit-registration-type-organization').click(function () {
        if ($(this).is(':checked')) {
            // Hide the Credit Card fields
            $('#edit-org-details').show();
            $('#edit-org-list').hide();
        }
    });

    $('#edit-registration-type-individual').click(function () {
        if ($(this).is(':checked')) {
            // Hide the Org Deatils fields
            $('#edit-org-details').hide('slow');
            // Show the Org list fields
            $('#edit-org-list').hide();
        }
    });

    $('#edit-registration-type-organization-memeber').click(function () {
        if ($(this).is(':checked')) {
            // Hide the Org Deatils fields
            $('#edit-org-details').hide('slow');
            // Show the Org list fields
            $('#edit-org-list').show();
        }
    });
})(jQuery);




