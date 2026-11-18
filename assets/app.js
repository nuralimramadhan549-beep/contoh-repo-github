document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('input[name="plat_nomor"]').forEach(function(el) {
    el.addEventListener('input', function() { this.value = this.value.toUpperCase(); });
  });
  document.querySelectorAll('.alert').forEach(function(el) {
    setTimeout(function() { el.style.opacity = '0'; setTimeout(function() { el.remove(); }, 500); }, 6000);
  });
});

function filterSlot(jenis, slotElementId) {
  const sel = document.getElementById(slotElementId);
  if (!sel || !window.__slots) return;
  sel.innerHTML = '<option value="">-- Pilih Slot --</option>';
  window.__slots.filter(s => s.jenis_kendaraan === jenis).forEach(s => {
    sel.innerHTML += `<option value="${s.id_slot}">${s.kode_slot} (Zona ${s.zona || 'A'})</option>`;
  });
}