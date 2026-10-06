{{-- Smartsupp live chat — official loader, unmodified.
     Included once per customer-facing layout (app, guest, public, welcome).
     Never rendered on /admin/* — the admin layout does not include it. --}}
<script type="text/javascript">
var _smartsupp = _smartsupp || {};
_smartsupp.key = '33dd28662ca78d7d06aa854de0e1f779bca5b650';
window.smartsupp||(function(d) {
  var s,c,o=smartsupp=function(){ o._.push(arguments)};o._=[];
  s=d.getElementsByTagName('script')[0];c=d.createElement('script');
  c.type='text/javascript';c.charset='utf-8';c.async=true;
  c.src='https://www.smartsuppchat.com/loader.js?';s.parentNode.insertBefore(c,s);
})(document);
</script>
<noscript>Powered by <a href="https://www.smartsupp.com" target="_blank">Smartsupp</a></noscript>
