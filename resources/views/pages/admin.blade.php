<div class="flex h-screen overflow-hidden bg-gray-50">
    <!-- Desktop/Mobile Sidebar -->
    <?php require __DIR__ . '/../partials/_sidebar_admin.blade.php'; ?>
    
    <!-- Main Wrapper -->
    <div class="flex-1 flex flex-col h-screen overflow-hidden relative w-full">
        <!-- Header -->
        <?php require __DIR__ . '/../partials/_header_admin.blade.php'; ?>

        <!-- Main Content -->
        <main class="flex-1 overflow-y-auto w-full px-4 lg:px-8 py-8 custom-scrollbar">
        <style>
            .tab-link.active-tab {
                background-color: #4f46e5 !important;
                color: white !important;
                box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2), 0 2px 4px -1px rgba(79, 70, 229, 0.1);
            }
            .tab-link.active-tab * {
                color: white !important;
            }
            .tab-link:not(.active-tab):hover {
                background-color: #eef2ff;
                color: #4f46e5;
            }
            .avatar-initials {
                width: 36px;
                height: 36px;
                flex-shrink: 0;
                background: linear-gradient(135deg, #4f46e5, #7c3aed);
                color: white;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 800;
                font-size: 14px;
                border-radius: 50%;
                box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2);
                aspect-ratio: 1/1;
                object-fit: cover;
            }
            .profile-avatar-img {
                width: 36px;
                height: 36px;
                flex-shrink: 0;
                border-radius: 50%;
                object-fit: cover;
                aspect-ratio: 1/1;
            }
        </style>

        <?php if (isAdmin()): ?>
            <?php try { require __DIR__ . '/kelola_member.blade.php'; } catch (Throwable $e) { error_log("Error loading kelola_member: " . $e->getMessage()); } ?>
            <?php try { require __DIR__ . '/data_presensi.blade.php'; } catch (Throwable $e) { error_log("Error loading data_presensi: " . $e->getMessage()); } ?>
            <?php try { require __DIR__ . '/laporan_bulanan_admin.blade.php'; } catch (Throwable $e) { error_log("Error loading laporan_bulanan_admin: " . $e->getMessage()); } ?>
            <?php try { require __DIR__ . '/settings.blade.php'; } catch (Throwable $e) { error_log("Error loading settings: " . $e->getMessage()); } ?>
            <?php try { require __DIR__ . '/dashboard.blade.php'; } catch (Throwable $e) { error_log("Error loading dashboard: " . $e->getMessage()); } ?>
            <?php try { require __DIR__ . '/admin_requests.blade.php'; } catch (Throwable $e) { error_log("Error loading admin_requests: " . $e->getMessage()); } ?>
        <?php endif; ?>

        <!-- QA Testing Panel (dedicated admin testing tool) -->
        <?php try { require __DIR__ . '/qa_panel.blade.php'; } catch (Throwable $e) { error_log("Error loading qa_panel: " . $e->getMessage()); } ?>


    </main>

    <!-- Modals -->
    <?php try { require __DIR__ . '/components/modals_common.blade.php'; } catch (Throwable $e) {} ?>
    <?php try { require __DIR__ . '/components/modals_admin.blade.php'; } catch (Throwable $e) {} ?>
    <?php try { require __DIR__ . '/components/modals_pegawai.blade.php'; } catch (Throwable $e) {} ?>

    </div>
</div>

<!-- Robot Cat Components for Preview Mode (Inlined for compatibility) -->
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




