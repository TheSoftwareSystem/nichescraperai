<?php if (isLoggedIn()): ?>
    </main>
    <footer class="border-t border-slate-700/50 px-6 py-4">
        <p class="text-xs text-slate-500">&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?></p>
    </footer>
</div><!-- /main-content -->
<?php endif; ?>
</body>
</html>
