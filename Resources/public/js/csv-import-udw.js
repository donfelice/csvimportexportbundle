(function () {
    const btns = document.querySelectorAll('.btn--udw-browse-csv');
    const udwContainer = document.getElementById('react-udw');
    const token = document.querySelector('meta[name="CSRF-Token"]').content;
    const siteaccess = document.querySelector('meta[name="SiteAccess"]').content;
    const closeUDW = () => udwContainer.innerHTML = '';

    const onConfirm = (items) => {
        closeUDW();

        //var step = document.getElementById('csv-card').data('step');
        var file_name = $('.card-body').data('file-name');
        var content_type = $('.card-body').data('content-type');
        var language = document.getElementById( 'select_language' ).value;

        console.log("Chosen language is: " + language);

        //window.location.href = window.Routing.generate('_ezpublishLocation', { locationId: items[0].id });
        //var content_type = document.getElementById( 'select_content_type' ).value;
        var url = "/admin/csv/import/3/" + file_name + "/" + content_type + "/" + items[0].id; // Step 3 is for actual importing
        console.log(url);

        window.location.href = url;
    };
    const onCancel = () => closeUDW();
    const openUDW = (event) => {

        console.log("Open UDW for CSV");

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
