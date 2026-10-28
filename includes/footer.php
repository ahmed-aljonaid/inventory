    </main>
  </div>
</div>
<script>
(function () {
  var btn = document.getElementById('menuBtn'), sb = document.getElementById('sidebar');
  if (btn) btn.addEventListener('click', function () {
    var open = sb.classList.toggle('open');
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
  document.addEventListener('submit', function (ev) {
    var msg = ev.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) ev.preventDefault();
  });
})();
</script>
</body>
</html>
