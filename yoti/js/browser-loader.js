(function(){
    // Create browser script tag.
    var script = document.createElement('script');
    script.type='text/javascript';
    script.async='async';
    script.src='https://sdk.yoti.com/clients/browser.2.2.0.js';

    // Initialise button once browser JS is loaded.
    script.addEventListener('load', function() {
        // Collect inline _ybg config.
        var config = window._ybg_config || {};
        for (i in config) {
            _ybg.config[i] = config[i];
        }
        _ybg.init();
    });

    document.head.appendChild(script);
})();
