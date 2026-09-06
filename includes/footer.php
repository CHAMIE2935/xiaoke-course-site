</main>

<footer class="site-footer">
  <div class="container footer-inner">
    <div>
      <div class="footer-logo"><?= e($GLOBALS['config']['site']['name'] ?? '小课学堂') ?></div>
      <p class="footer-slogan"><?= e($GLOBALS['config']['site']['slogan'] ?? '让学习更高效') ?></p>
    </div>
    <div class="footer-links">
      <a href="<?= url('courses.php') ?>">全部课程</a>
      <a href="<?= url('user.php') ?>">我的学习</a>
    </div>
    <p class="footer-copy">
    <a href="https://github.com/CHAMIE2935" target="_blank">
    © <?= date('Y') ?> <?= e($GLOBALS['config']['site']['name'] ?? '小课学堂') ?>
</a>
&nbsp;|&nbsp;
<a href="https://github.com/CHAMIE2935" target="_blank">
    网站源码
</a>

</p>

  </div>
</footer>
<script src="<?= url('assets/js/main.js') ?>"></script>
</body>
</html>
