</div> <div class="mt-12 py-6 border-t border-slate-200 text-center">
                <p class="text-xs text-slate-400">
                    &copy; <?= date('Y'); ?> Denny Ardi. Expense Tracker.
                </p>
            </div>

        </main> <?php if (isset($_SESSION['user_id'])): ?>
    </div> </div> <script>
        const sidebar = document.getElementById('sidebar');
        const openBtn = document.getElementById('openSidebar');
        const closeBtn = document.getElementById('closeSidebar');
        const contentOverlay = document.createElement('div'); 

        // Setup Overlay
        contentOverlay.className = 'fixed inset-0 bg-slate-900/50 z-40 hidden md:hidden glass-effect';
        if(sidebar) document.body.appendChild(contentOverlay);

        function toggleSidebar() {
            if (!sidebar) return;
            const isClosed = sidebar.classList.contains('-translate-x-full');
            if (isClosed) {
                sidebar.classList.remove('-translate-x-full');
                contentOverlay.classList.remove('hidden');
            } else {
                sidebar.classList.add('-translate-x-full');
                contentOverlay.classList.add('hidden');
            }
        }

        if (openBtn) openBtn.addEventListener('click', toggleSidebar);
        if (closeBtn) closeBtn.addEventListener('click', toggleSidebar);
        if (contentOverlay) contentOverlay.addEventListener('click', toggleSidebar);
    </script>
    <?php endif; ?>

</body>
</html>