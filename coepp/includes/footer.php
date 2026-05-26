<?php
// includes/footer.php
// OBS: Estrutura final do layout (fecha <main>) e renderiza rodapé padronizado.
// OBS: Inclui o painel lateral reutilizável (info_modal) para pacientes/estagiários.
?>
</main>

<footer class="footer">
  <div style="text-align:center;color:#6b7280;font-size:14px;padding:18px 0 8px;">
    © <?= date('Y') ?> COEPP - Produzido por CodeLink - Periodo de Testes
  </div>
</footer>

<?php
// painel lateral reutilizável de informações (pacientes/estagiários)
// OBS: Mantém o drawer disponível em todas as páginas que incluem o footer.
include ROOT . '/includes/info_modal.php';
?>

</body>
</html>