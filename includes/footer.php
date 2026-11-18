    </main> <!-- Penutup tag <main class="content"> -->

    <!-- FOOTER KONTEN -->
    <footer style="padding: 18px 20px; text-align: center; font-size: 12.5px; color: #94a3b8; border-top: 1px solid rgba(255,255,255,0.08); background: #0b0f1a; margin-top: auto;">
      &copy; <?= date('Y') ?> <b style="color: #ffffff;"><?= e($perusahaan['nama_perusahaan'] ?? 'PT. Parkir Bogor') ?></b> · v<?= defined('APP_VERSION') ? APP_VERSION : '1.0' ?>
    </footer>

  </div> <!-- Penutup <div class="main"> -->
</div> <!-- Penutup <div class="app-layout"> -->

<script src="<?= BASE_URL ?>assets/app.js?v=<?= time() ?>"></script>
<script>
(function() {
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('overlay');
  const btnToggle = document.getElementById('btnToggleSidebar');
  const btnClose = document.getElementById('btnCloseSidebar');
  
  function openSidebar() {
    if (sidebar) sidebar.classList.add('active');
    if (overlay) overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function closeSidebar() {
    if (sidebar) sidebar.classList.remove('active');
    if (overlay) overlay.classList.remove('active');
    document.body.style.overflow = '';
  }

  if (btnToggle) {
    btnToggle.addEventListener('click', function(e) {
      e.stopPropagation();
      if (sidebar && sidebar.classList.contains('active')) {
        closeSidebar();
      } else {
        openSidebar();
      }
    });
  }

  if (btnClose) {
    btnClose.addEventListener('click', closeSidebar);
  }

  if (overlay) {
    overlay.addEventListener('click', closeSidebar);
  }

  // Close sidebar on menu link tap (especially useful for single page / hash navigation)
  if (sidebar) {
    sidebar.querySelectorAll('a').forEach(function(link) {
      link.addEventListener('click', function() {
        if (window.innerWidth <= 1024) {
          closeSidebar();
        }
      });
    });
  }

  // Close on ESC
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && sidebar && sidebar.classList.contains('active')) {
      closeSidebar();
    }
  });
})();
</script>
</body>
</html>