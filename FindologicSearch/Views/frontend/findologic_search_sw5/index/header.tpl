{block name="frontend_index_header_javascript_modernizr_lib" prepend}
   <script type="text/javascript">
    (function() {
        var flDataMain = "https://cdn.findologic.com/autocomplete/{$placeholder1}/autocomplete.js{if $placeholder2 != ''}?usergrouphash={$placeholder2}{/if}";
        var flAutocomplete = document.createElement('script'); 
        flAutocomplete.type = 'text/javascript'; 
        flAutocomplete.async = true;
        flAutocomplete.src = "https://cdn.findologic.com/autocomplete/require.js";
        var s = document.getElementsByTagName('script')[0];
        flAutocomplete.setAttribute('data-main', flDataMain);
        s.parentNode.insertBefore(flAutocomplete, s);
    })();
    </script>
    <script type="text/javascript">
    var fl_paq = fl_paq || [];
    (function(){ var u=(("https:" == document.location.protocol) ? "https://tracking.findologic.com/" : "http://tracking.findologic.com/");
    fl_paq.push(['setSiteId', '{$placeholder1}']);
    fl_paq.push(['setTrackerUrl', u+'tracking.php']);
    fl_paq.push(['trackPageView']);
    fl_paq.push(['enableLinkTracking']);
    var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0]; g.type='text/javascript'; g.defer=true; g.async=true; g.src=u+'tracking.js';
    s.parentNode.insertBefore(g,s); })();
</script>
{/block}  

