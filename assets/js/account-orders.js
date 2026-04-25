document.addEventListener('DOMContentLoaded', function () {
    if (typeof dcafAccountUi === 'undefined') {
        return;
    }

    var tables = document.querySelectorAll('.woocommerce-account .woocommerce-orders-table');

    tables.forEach(function (table) {
        var statusCells = table.querySelectorAll('.woocommerce-orders-table__cell-order-status');

        statusCells.forEach(function (cell) {
            var raw = (cell.textContent || '').trim().toLowerCase();

            if (raw === 'cancelled' || raw === 'storniert') {
                cell.textContent = '';

                var badge = document.createElement('span');
                badge.className = 'dcaf-status-badge';
                badge.textContent = dcafAccountUi.cancelledLabel;
                badge.setAttribute('aria-label', dcafAccountUi.cancelledLabel);

                cell.appendChild(badge);
            }
        });
    });
});
