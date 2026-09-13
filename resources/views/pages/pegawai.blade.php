<div class="flex h-screen overflow-hidden bg-gray-50">
    <!-- Desktop/Mobile Sidebar -->
    <?php require __DIR__ . '/../partials/_sidebar_pegawai.blade.php'; ?>
    
    <!-- Main Wrapper -->
    <div class="flex-1 flex flex-col h-screen overflow-hidden relative w-full">
        <!-- Header -->
        <?php require __DIR__ . '/../partials/_header_pegawai.blade.php'; ?>

        <!-- Main Content -->
        <main class="flex-1 overflow-y-auto w-full px-4 lg:px-8 py-8 custom-scrollbar">
        <?php if (!isAdmin()): ?>
        <!-- Hero Banner for Pegawai -->
        <?php require __DIR__ . '/components/robot_cat.blade.php'; ?>
        <?php endif; ?>
        
        <!-- Pegawai: Rekap Hadir -->
        <?php try { require __DIR__ . '/rekap_hadir.blade.php'; } catch (Throwable $e) {} ?>

        <!-- Pegawai: Laporan Bulanan -->
        <?php try { require __DIR__ . '/laporan_bulanan_pegawai.blade.php'; } catch (Throwable $e) {} ?>

        <?php if (isAdmin()): ?>
            <?php try { require __DIR__ . '/kelola_member.blade.php'; } catch (Throwable $e) {} ?>
            <?php try { require __DIR__ . '/data_presensi.blade.php'; } catch (Throwable $e) {} ?>
            <?php try { require __DIR__ . '/laporan_bulanan_admin.blade.php'; } catch (Throwable $e) {} ?>
            <?php try { require __DIR__ . '/settings.blade.php'; } catch (Throwable $e) {} ?>
            <?php try { require __DIR__ . '/dashboard.blade.php'; } catch (Throwable $e) {} ?>
        <?php endif; ?>
        </main>
    </div>
</div>


    <!-- Modals -->
    <?php try { require __DIR__ . '/components/modals_common.blade.php'; } catch (Throwable $e) {} ?>
    <?php try { require __DIR__ . '/components/modals_pegawai.blade.php'; } catch (Throwable $e) {} ?>
    <?php try { require __DIR__ . '/components/modals_admin.blade.php'; } catch (Throwable $e) {} ?>
    <?php try { require __DIR__ . '/components/admin_help_center.blade.php'; } catch (Throwable $e) {} ?>


    <!-- Robot Cat Components (Inlined for compatibility) -->
    <style id="robot-cat-styles">
        <?php 
        $css_path = public_path('assets/css/robot_cat_animations.css');
        if (file_exists($css_path)) {
            echo file_get_contents($css_path);
        } else {
            echo "/* Error: CSS file not found at $css_path */";
        }
        ?>
    </style>
    <script id="robot-cat-script">
        <?php 
        $js_path = public_path('assets/js/robot_cat_character.js');
        if (file_exists($js_path)) {
            echo file_get_contents($js_path);
        } else {
            echo "console.error('Error: JS file not found at $js_path');";
        }
        ?>
    </script>


