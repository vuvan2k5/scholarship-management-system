    </div><!-- /page-body -->
  </div><!-- /main-content -->
</div><!-- /app-shell -->

<!-- Bootstrap Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous" defer></script>

<!-- Custom JS -->
<script src="<?= BASE_URL ?>/assets/js/validation.js"></script>

<script>
// Mobile sidebar toggle
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebar = document.getElementById('sidebar');
if (sidebarToggle && sidebar) {
  sidebarToggle.addEventListener('click', () => sidebar.classList.toggle('open'));
  document.addEventListener('click', (e) => {
    if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
      sidebar.classList.remove('open');
    }
  });
}
</script>

</body>
</html>
