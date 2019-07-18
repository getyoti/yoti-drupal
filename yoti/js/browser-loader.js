(function(){
    // Create browser script tag.
    var script = document.createElement('script');
    script.type='text/javascript';
    script.async='async';
    script.src='https://www.yoti.com/share/client/';

    // Initialise button once browser JS is loaded.
    script.addEventListener('load', function() {
        window.Yoti.Share.init(yotiConfig);
    });

    document.head.appendChild(script);
})();
