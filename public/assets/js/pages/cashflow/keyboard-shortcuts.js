'use strict';

(function () {
    $(document).on('keydown', function (e) {
        // Alt+E → Navigate to Add Expense
        if (e.altKey && e.key === 'e') {
            e.preventDefault();
            var expenseUrl = $('a[href*="cashflow/expenses"]').attr('href');
            if (expenseUrl) window.location.href = expenseUrl;
        }

        // Alt+T → Navigate to Add Transfer
        if (e.altKey && e.key === 't') {
            e.preventDefault();
            var transferUrl = $('a[href*="cashflow/transfers"]').attr('href');
            if (transferUrl) window.location.href = transferUrl;
        }

        // Ctrl+Enter → Submit the visible modal form
        if (e.ctrlKey && e.key === 'Enter') {
            var visibleModal = $('.modal.show');
            if (visibleModal.length) {
                e.preventDefault();
                var submitBtn = visibleModal.find('.modal-footer .btn-primary:visible');
                if (submitBtn.length) submitBtn.first().trigger('click');
            }
        }

        // Escape → Close visible modal
        if (e.key === 'Escape') {
            var openModal = $('.modal.show');
            if (openModal.length) {
                openModal.modal('hide');
            }
        }
    });
})();
