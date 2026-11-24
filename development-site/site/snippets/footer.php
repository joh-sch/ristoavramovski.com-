  <?php // Scripts // ?>
  <?= js('app/app.min.js?v=' . RISTO_2026_VERSION, ['defer' => true]) ?>

  <?php // Livereload — enabled via the local config, requires `npm run reload` // ?>
  <?php if (option('risto.livereload') === true): ?>
    <script src="http://localhost:35729/livereload.js?snipver=1"></script>
  <?php endif ?>
</body>
</html>
