(function () {
    const btns = document.querySelectorAll('.btn--udw-browse-csv-export');
    const udwContainer = document.getElementById('react-udw');
    const token = document.querySelector('meta[name="CSRF-Token"]').content;
    const siteaccess = document.querySelector('meta[name="SiteAccess"]').content;
    const closeUDW = () => udwContainer.innerHTML = '';

    const onConfirm = (items) => {
        closeUDW();

        var url = "/admin/csv/export/1/" + items[0].id; // Step 3 is for actual importing
        window.location.href = url;
    };
    const onCancel = () => closeUDW();
    const openUDW = (event) => {

        event.preventDefault();

        ReactDOM.render(React.createElement(eZ.modules.UniversalDiscovery, {
            onConfirm,
            onCancel,
            confirmLabel: 'View content',
            title: 'Browse content',
            multiple: false,
            startingLocationId: parseInt(event.currentTarget.dataset.startingLocationId, 10),
            restInfo: {token, siteaccess}
        }), udwContainer);
    };

    btns.forEach(btn => btn.addEventListener('click', openUDW, false));
})();
