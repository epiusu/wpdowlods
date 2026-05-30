<?php
/**
 * WordPress ISO İndirme Sayfası - v10 (Modern UI, Tam Responsive, Ortalanmış)
 * 
 * Kurulum:
 * 1. Bu dosyayı tema klasörünüze iso-downloads.php olarak yükleyin
 * 2. functions.php dosyanıza ekleyin: require_once get_template_directory() . '/iso-downloads.php';
 * 3. WordPress Admin -> Sayfalar -> Yeni Ekle kısmından sayfa oluşturun.
 *    - Başlık: İndirmeler (İstediğiniz gibi)
 *    - Kalıcı Bağlantı (Slug): downloads (ÖNEMLİ: URL'nin siteniz.com/downloads/ olması için)
 *    - İçerik: [downloads] yazın.
 */

if (!defined('ABSPATH')) exit;

/* ═══════════════════════════════════════════════════════
   ÜLKE LİSTESİ
   ═══════════════════════════════════════════════════════ */

function iso_get_countries(): array {
    return array(
        'Turkiye'=>'🇹🇷 Türkiye','USA'=>'🇺🇸 USA','Germany'=>'🇩🇪 Germany',
        'Netherlands'=>'🇳🇱 Netherlands','France'=>'🇫🇷 France','UK'=>'🇬🇧 United Kingdom',
        'Japan'=>'🇯🇵 Japan','Finland'=>'🇫🇮 Finland','Canada'=>'🇨🇦 Canada',
        'Australia'=>'🇦🇺 Australia','SouthKorea'=>'🇰🇷 South Korea','Singapore'=>'🇸🇬 Singapore',
        'Brazil'=>'🇧🇷 Brazil','India'=>'🇮🇳 India','Russia'=>'🇷🇺 Russia',
        'Italy'=>'🇮🇹 Italy','Spain'=>'🇪🇸 Spain','Sweden'=>'🇸🇪 Sweden',
        'Switzerland'=>'🇨🇭 Switzerland','Poland'=>'🇵🇱 Poland','Norway'=>'🇳🇴 Norway',
        'Denmark'=>'🇩🇰 Denmark','Austria'=>'🇦🇹 Austria','Belgium'=>'🇧🇪 Belgium',
    );
}

function iso_get_flag_map(): array {
    return array(
        'Turkiye'=>'🇹🇷','USA'=>'🇺🇸','Germany'=>'🇩🇪','Netherlands'=>'🇳🇱',
        'France'=>'🇫🇷','UK'=>'🇬🇧','Japan'=>'🇯🇵','Finland'=>'🇫🇮',
        'Canada'=>'🇨🇦','Australia'=>'🇦🇺','SouthKorea'=>'🇰🇷','Singapore'=>'🇸🇬',
        'Brazil'=>'🇧🇷','India'=>'🇮🇳','Russia'=>'🇷🇺','Italy'=>'🇮🇹',
        'Spain'=>'🇪🇸','Sweden'=>'🇸🇪','Switzerland'=>'🇨🇭','Poland'=>'🇵🇱',
        'Norway'=>'🇳🇴','Denmark'=>'🇩🇰','Austria'=>'🇦🇹','Belgium'=>'🇧🇪',
    );
}

/* ═══════════════════════════════════════════════════════
   AJAX: İNDİRME SAYACI
   ═══════════════════════════════════════════════════════ */

add_action('wp_ajax_iso_track_dl', 'iso_track_dl');
add_action('wp_ajax_nopriv_iso_track_dl', 'iso_track_dl');
function iso_track_dl(): void {
    $id = sanitize_text_field($_POST['iso_id'] ?? '');
    if(empty($id)) wp_die();
    $stats = get_option('iso_dl_stats', array());
    if(!isset($stats[$id])) $stats[$id] = 0;
    $stats[$id]++;
    update_option('iso_dl_stats', $stats);
    wp_die();
}

/* ═══════════════════════════════════════════════════════
   AJAX: ADMIN GLOBAL AYAR (GÖSTER/GİZLE)
   ═══════════════════════════════════════════════════════ */

add_action('wp_ajax_iso_toggle_visibility', 'iso_toggle_visibility');
function iso_toggle_visibility(): void {
    check_ajax_referer('iso_dl_settings_nonce', 'security');
    if (!current_user_can('manage_options')) wp_send_json_error('Yetkisiz');
    $visible = isset($_POST['is_visible']) && $_POST['is_visible'] === 'true' ? 1 : 0;
    update_option('iso_dl_visible', $visible);
    wp_send_json_success();
}

/* ═══════════════════════════════════════════════════════
   AJAX: ADMIN ISO KAYIT
   ═══════════════════════════════════════════════════════ */

add_action('wp_ajax_iso_save_admin_item', 'iso_ajax_save_admin_item');
function iso_ajax_save_admin_item(): void {
    check_ajax_referer('iso_dl_save', 'iso_nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Yetkisiz');

    $option_key = 'iso_dl_items';
    $items = get_option($option_key, array());
    $id = isset($_POST['iso_id']) && !empty($_POST['iso_id']) ? sanitize_text_field($_POST['iso_id']) : uniqid('iso_');

    $mirrors = array();
    $m_countries = isset($_POST['mirror_country']) ? array_map('sanitize_text_field', $_POST['mirror_country']) : array();
    $m_urls = isset($_POST['mirror_url']) ? array_map('esc_url_raw', $_POST['mirror_url']) : array();
    foreach ($m_countries as $i => $country) {
        if (!empty($m_urls[$i])) $mirrors[] = array('country' => $country, 'url' => $m_urls[$i]);
    }

    $torrents = array();
    if (isset($_POST['torrents']) && is_array($_POST['torrents'])) {
        foreach ($_POST['torrents'] as $t) { $t = sanitize_text_field($t); if (!empty($t)) $torrents[] = $t; }
    }

    $verification_content = isset($_POST['verification_content']) ? sanitize_textarea_field(wp_unslash($_POST['verification_content'])) : '';

    $items[$id] = array(
        'id'                  => $id,
        'title'               => sanitize_text_field(wp_unslash($_POST['title'] ?? '')),
        'version'             => sanitize_text_field($_POST['version'] ?? ''),
        'desc'                => wp_kses_post(wp_unslash($_POST['desc'] ?? '')),
        'image_url'           => esc_url_raw($_POST['image_url'] ?? ''),
        'website_url'         => esc_url_raw($_POST['website_url'] ?? ''),
        'accent_color'        => sanitize_hex_color($_POST['accent_color'] ?? '#2563eb'),
        'btn_bg_color'        => sanitize_hex_color($_POST['btn_bg_color'] ?? '#059669'),
        'btn_txt_color'       => sanitize_hex_color($_POST['btn_txt_color'] ?? '#ffffff'),
        'md5'                 => sanitize_text_field($_POST['md5'] ?? ''),
        'sha1'                => sanitize_text_field($_POST['sha1'] ?? ''),
        'sha256'              => sanitize_text_field($_POST['sha256'] ?? ''),
        'verification_content'=> $verification_content,
        'mirrors'             => $mirrors,
        'torrents'            => $torrents,
        'featured'            => isset($_POST['featured']),
    );

    uasort($items, function($a, $b) {
        $a_feat = !empty($a['featured']) ? 1 : 0;
        $b_feat = !empty($b['featured']) ? 1 : 0;
        if ($a_feat !== $b_feat) return $b_feat - $a_feat;
        return version_compare($b['version'], $a['version']);
    });
    
    update_option($option_key, $items);
    wp_send_json_success(array('id' => $id));
}

/* ═══════════════════════════════════════════════════════
   YÖNETİM PANELİ
   ═══════════════════════════════════════════════════════ */

add_action('admin_menu', 'iso_dl_admin_menu');
function iso_dl_admin_menu(): void {
    add_theme_page('ISO Ayarları', 'ISO Ayarları', 'manage_options', 'iso-downloads', 'iso_dl_admin_page');
    add_theme_page('İndirme İstatistikleri', 'İndirme İstatistikleri', 'manage_options', 'iso-stats', 'iso_dl_stats_page');
}

add_action('admin_enqueue_scripts', 'iso_dl_admin_assets');
function iso_dl_admin_assets($hook): void {
    if (strpos($hook, 'iso-downloads') === false && strpos($hook, 'iso-stats') === false) return;
    wp_enqueue_media();
    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('wp-color-picker');
    wp_enqueue_style('iso-fa-admin', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css', array(), '6.5.1');
    
    wp_add_inline_style('dashicons', '
    :root {
        --admin-bg: #f8fafc;
        --surface: #ffffff;
        --border: #e2e8f0;
        --text: #1e293b;
        --text-secondary: #64748b;
        --primary: #3b82f6;
        --primary-hover: #2563eb;
        --success: #10b981;
        --danger: #ef4444;
        --radius-sm: 0.75rem;
        --radius: 1rem;
        --radius-lg: 1.25rem;
        --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
        --shadow: 0 1px 3px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.06);
        --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -2px rgba(0,0,0,0.1);
        --transition: 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .iso-admin-wrap {
        max-width: 1000px;
        margin: 1.5rem auto 2.5rem;
        font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        color: var(--text);
    }
    .iso-admin-wrap * { box-sizing: border-box; }
    
    .iso-admin-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1.75rem 2rem;
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        border-radius: var(--radius-lg);
        margin-bottom: 1.5rem;
        color: #f1f5f9;
        box-shadow: var(--shadow-md);
    }
    .iso-admin-header h1 {
        font-size: 1.5rem;
        font-weight: 700;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    .iso-admin-header h1 i { color: #60a5fa; font-size: 1.25rem; }
    .iso-admin-header p { margin: 0.25rem 0 0; font-size: 0.875rem; color: #94a3b8; }
    
    .iso-add-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.75rem;
        background: var(--success);
        color: #fff;
        border: none;
        border-radius: 0.75rem;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
        box-shadow: 0 1px 2px rgba(0,0,0,0.1);
    }
    .iso-add-btn:hover { background: #059669; transform: translateY(-1px); box-shadow: var(--shadow-md); }
    
    .iso-shortcode-bar {
        background: #ecfdf5;
        border: 1px solid #a7f3d0;
        border-radius: var(--radius);
        padding: 1rem 1.5rem;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-size: 0.875rem;
        color: #065f46;
    }
    .iso-shortcode-bar code {
        background: #d1fae5;
        padding: 0.25rem 0.75rem;
        border-radius: 0.5rem;
        font-weight: 600;
    }
    
    .iso-global-settings {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 1.25rem 1.75rem;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
        box-shadow: var(--shadow-sm);
    }
    .iso-global-settings h3 {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text);
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin: 0;
    }
    .iso-global-settings h3 i { color: #f59e0b; }
    .iso-toggle-wrap { display: flex; align-items: center; gap: 0.75rem; }
    .iso-toggle-label { font-size: 0.875rem; font-weight: 500; color: var(--text-secondary); }
    
    /* Switch */
    .iso-switch { position: relative; display: inline-block; width: 48px; height: 26px; }
    .iso-switch input { opacity: 0; width: 0; height: 0; }
    .iso-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .3s; border-radius: 26px; }
    .iso-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 4px; bottom: 4px; background-color: white; transition: .3s; border-radius: 50%; }
    .iso-switch input:checked + .iso-slider { background-color: var(--success); }
    .iso-switch input:checked + .iso-slider:before { transform: translateX(22px); }
    
    .iso-list { display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1.5rem; }
    .iso-list-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 1.25rem 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        transition: var(--transition);
        box-shadow: var(--shadow-sm);
    }
    .iso-list-card:hover {
        border-color: #94a3b8;
        box-shadow: var(--shadow-md);
        transform: translateY(-1px);
    }
    .iso-list-icon {
        width: 48px; height: 48px;
        border-radius: var(--radius-sm);
        background: #eff6ff;
        color: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
    }
    .iso-list-info { flex: 1; min-width: 0; }
    .iso-list-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .iso-list-title .badge {
        font-size: 0.7rem;
        background: var(--primary);
        color: #fff;
        padding: 0.2rem 0.6rem;
        border-radius: 100px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    .iso-list-meta {
        font-size: 0.8rem;
        color: var(--text-secondary);
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
        margin-top: 0.25rem;
    }
    .iso-list-actions { display: flex; gap: 0.5rem; flex-shrink: 0; }
    .iso-list-actions a, .iso-list-actions button {
        width: 36px; height: 36px;
        border-radius: 0.5rem;
        border: 1px solid var(--border);
        background: var(--surface);
        color: var(--text-secondary);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
        transition: var(--transition);
        text-decoration: none;
    }
    .iso-list-actions a:hover, .iso-list-actions button:hover {
        background: #f1f5f9;
        color: var(--text);
        border-color: #94a3b8;
    }
    .iso-list-actions a.del:hover {
        background: #fef2f2;
        color: var(--danger);
        border-color: #fca5a5;
    }
    
    .iso-empty-state {
        text-align: center;
        padding: 4rem 1.5rem;
        background: var(--surface);
        border: 1px dashed #cbd5e1;
        border-radius: var(--radius-lg);
    }
    .iso-empty-state i { font-size: 2.5rem; color: #cbd5e1; margin-bottom: 0.75rem; }
    .iso-empty-state h3 { font-size: 1.1rem; color: var(--text-secondary); margin-bottom: 0.25rem; }
    
    /* Form Panel */
    .iso-form-panel {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        overflow: hidden;
        display: none;
        box-shadow: var(--shadow);
        animation: isoSlideDown 0.3s ease;
    }
    .iso-form-panel.open { display: block; }
    @keyframes isoSlideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    .iso-form-header {
        background: #f8fafc;
        border-bottom: 1px solid var(--border);
        padding: 1.25rem 1.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .iso-form-header h2 {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .iso-close-form {
        width: 32px; height: 32px;
        border-radius: 0.5rem;
        border: 1px solid var(--border);
        background: #fff;
        color: var(--text-secondary);
        cursor: pointer;
        font-size: 0.9rem;
        transition: var(--transition);
    }
    .iso-close-form:hover { background: #fef2f2; color: var(--danger); border-color: #fca5a5; }
    
    .iso-form-body { padding: 1.5rem; }
    .iso-section { margin-bottom: 1.5rem; }
    .iso-section-head {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid #f1f5f9;
    }
    .iso-section-head i {
        width: 28px; height: 28px;
        border-radius: 0.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        color: #fff;
        flex-shrink: 0;
    }
    .iso-section-head span { font-size: 0.95rem; font-weight: 600; color: var(--text); }
    
    .iso-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .iso-form-full { grid-column: 1 / -1; }
    
    .iso-field { display: flex; flex-direction: column; gap: 0.35rem; }
    .iso-field label { font-size: 0.8rem; font-weight: 500; color: var(--text-secondary); }
    .iso-field input[type="text"], .iso-field input[type="url"], .iso-field textarea, .iso-field select {
        width: 100%;
        padding: 0.6rem 0.8rem;
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        font-family: inherit;
        color: var(--text);
        background: #fff;
        outline: none;
        transition: var(--transition);
    }
    .iso-field input:focus, .iso-field textarea:focus, .iso-field select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
    }
    .iso-field .checkbox-wrap { display: flex; align-items: center; gap: 0.5rem; padding-top: 0.25rem; }
    .iso-field .checkbox-wrap input[type="checkbox"] { width: 16px; height: 16px; accent-color: var(--primary); }
    
    .iso-dynamic-rows { display: flex; flex-direction: column; gap: 0.5rem; }
    .iso-dynamic-row { display: flex; align-items: center; gap: 0.5rem; }
    .iso-dynamic-row select { min-width: 140px; }
    .iso-dynamic-row input { flex: 1; min-width: 0; }
    .iso-row-btn {
        width: 34px; height: 34px;
        border-radius: 0.5rem;
        border: 1px solid #d1d5db;
        background: #f9fafb;
        color: var(--text-secondary);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
        flex-shrink: 0;
        transition: var(--transition);
    }
    .iso-row-btn.iso-add-row { background: #eff6ff; color: var(--primary); border-color: #bfdbfe; font-weight: 700; }
    .iso-row-btn.iso-remove-row:hover { background: #fef2f2; color: var(--danger); border-color: #fca5a5; }
    
    .iso-form-footer {
        padding: 1rem 1.5rem;
        background: #f8fafc;
        border-top: 1px solid var(--border);
        display: flex;
        align-items: center;
        gap: 0.75rem;
        justify-content: flex-end;
    }
    .iso-save-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.6rem 1.75rem;
        background: var(--primary);
        color: #fff;
        border: none;
        border-radius: 0.5rem;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
    }
    .iso-save-btn:hover { background: var(--primary-hover); transform: translateY(-1px); }
    .iso-cancel-btn {
        padding: 0.6rem 1.5rem;
        background: #fff;
        color: var(--text-secondary);
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
    }
    .iso-cancel-btn:hover { background: #f9fafb; }
    
    .iso-saved-msg {
        display: none;
        padding: 0.75rem 1rem;
        background: #ecfdf5;
        color: #065f46;
        border: 1px solid #a7f3d0;
        border-radius: 0.5rem;
        margin-bottom: 1.25rem;
        font-weight: 500;
    }
    
    /* Stats Table */
    .iso-stats-table { width: 100%; border-collapse: collapse; background: var(--surface); border-radius: var(--radius); overflow: hidden; border: 1px solid var(--border); }
    .iso-stats-table th, .iso-stats-table td { padding: 0.9rem 1.25rem; text-align: left; border-bottom: 1px solid #f1f5f9; }
    .iso-stats-table th { background: #f8fafc; font-weight: 600; color: var(--text); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; }
    .iso-stats-table td { font-size: 0.9rem; color: #374151; }
    .iso-stats-num { font-weight: 700; color: var(--primary); font-size: 1rem; }
    
    @media (max-width: 640px) {
        .iso-form-grid { grid-template-columns: 1fr; }
        .iso-dynamic-row { flex-wrap: wrap; }
        .iso-dynamic-row select { min-width: 100%; }
        .iso-admin-header { flex-direction: column; gap: 1rem; text-align: center; }
        .iso-global-settings { flex-direction: column; align-items: flex-start; }
        .iso-list-card { flex-wrap: wrap; }
    }
    ');
}

/* ═══════════════════════════════════════════════════════
   ADMIN: ISO AYARLARI SAYFASI
   ═══════════════════════════════════════════════════════ */

function iso_dl_admin_page(): void {
    if (!current_user_can('manage_options')) return;
    $option_key = 'iso_dl_items';
    $is_visible = get_option('iso_dl_visible', 1);

    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && wp_verify_nonce($_GET['_wpnonce'] ?? '', 'delete_iso_' . $_GET['id'])) {
        $items = get_option($option_key, array());
        $id = sanitize_text_field($_GET['id']);
        if (isset($items[$id])) { unset($items[$id]); update_option($option_key, $items); }
        echo '<script>window.location="' . admin_url('themes.php?page=iso-downloads') . '";</script>'; exit;
    }

    $items = get_option($option_key, array());
    $edit_item = null;
    if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
        $id = sanitize_text_field($_GET['id']);
        if (isset($items[$id])) $edit_item = $items[$id];
    }
    $countries = iso_get_countries();
    ?>

    <div class="iso-admin-wrap">
        <div class="iso-admin-header">
            <div>
                <h1><i class="fa-solid fa-compact-disc"></i> ISO İndirme Yönetimi</h1>
                <p>İşletim sistemi imajlarını yönetin ve yayınlayın</p>
            </div>
            <button class="iso-add-btn" onclick="isoToggleForm()" id="iso-toggle-btn"><i class="fa-solid fa-plus"></i> Yeni ISO Ekle</button>
        </div>

        <div class="iso-shortcode-bar">
            <i class="fa-solid fa-code"></i> 
            URL'nin <strong>https://siteniz.com/downloads/</strong> olması için WordPress'te yeni bir sayfa oluşturun. Başlığı "İndirmeler" yapın, <strong>Kalıcı Bağlantı (URL)</strong> kısmının <code>downloads</code> olduğuna emin olun ve içeriğine sadece <code>[downloads]</code> yazın.
        </div>

        <!-- GÖSTER / GİZLE ANAHTARI -->
        <div class="iso-global-settings">
            <h3><i class="fa-solid fa-eye"></i> İndirme Sayfası Görünürlüğü</h3>
            <div class="iso-toggle-wrap">
                <span class="iso-toggle-label"><?php echo $is_visible ? 'Aktif' : 'Bakım Modunda'; ?></span>
                <label class="iso-switch">
                    <input type="checkbox" id="iso-visibility-toggle" <?php checked($is_visible, 1); ?>>
                    <span class="iso-slider"></span>
                </label>
            </div>
        </div>

        <div class="iso-saved-msg" id="iso-saved-msg">Başarıyla kaydedildi!</div>

        <div class="iso-list" id="iso-list">
            <?php if (empty($items)): ?>
            <div class="iso-empty-state"><i class="fa-solid fa-compact-disc"></i><h3>Henüz ISO eklenmemiş</h3></div>
            <?php else: foreach($items as $item): $mc = count($item['mirrors']); ?>
            <div class="iso-list-card">
                <div class="iso-list-icon" style="<?php if(!empty($item['accent_color'])) echo 'color:'.$item['accent_color'].';background:'.$item['accent_color'].'15'; ?>"><i class="fa-solid fa-compact-disc"></i></div>
                <div class="iso-list-info">
                    <div class="iso-list-title"><?php echo esc_html($item['title']); ?> <?php if(!empty($item['featured'])): ?><span class="badge">📌 Öne Çıkan</span><?php endif; ?></div>
                    <div class="iso-list-meta">
                        <span><i class="fa-solid fa-code-branch"></i> v<?php echo esc_html($item['version']); ?></span>
                    </div>
                </div>
                <div class="iso-list-actions">
                    <button onclick="isoEditItem('<?php echo esc_attr($item['id']); ?>')" title="Düzenle"><i class="fa-solid fa-pen"></i></button>
                    <a href="<?php echo wp_nonce_url(admin_url('themes.php?page=iso-downloads&action=delete&id='.$item['id']), 'delete_iso_'.$item['id']); ?>" onclick="return confirm('Silmek istediğinize emin misiniz?');" class="del" title="Sil"><i class="fa-solid fa-trash"></i></a>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>

        <div class="iso-form-panel<?php echo $edit_item ? ' open' : ''; ?>" id="iso-form-panel">
            <div class="iso-form-header">
                <h2><i class="fa-solid fa-pen-to-square"></i> <span id="iso-form-title"><?php echo $edit_item ? 'ISO Düzenle' : 'Yeni ISO Ekle'; ?></span></h2>
                <button class="iso-close-form" onclick="isoToggleForm(true)"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form id="iso-form" method="post">
                <?php wp_nonce_field('iso_dl_save', 'iso_nonce'); ?>
                <input type="hidden" name="action" value="iso_save_admin_item">
                <input type="hidden" name="iso_id" id="iso_id_field" value="<?php echo $edit_item ? esc_attr($edit_item['id']) : ''; ?>">
                <div class="iso-form-body">
                    
                    <div class="iso-section iso-sec-basic">
                        <div class="iso-section-head"><i class="fa-solid fa-info-circle"></i><span>Temel Bilgiler</span></div>
                        <div class="iso-form-grid">
                            <div class="iso-field"><label>ISO Başlığı</label><input type="text" name="title" id="f_title" required value="<?php echo $edit_item ? esc_attr($edit_item['title'] ?? '') : ''; ?>" placeholder="Ubuntu 24.04 LTS"></div>
                            <div class="iso-field"><label>Versiyon</label><input type="text" name="version" id="f_version" required value="<?php echo $edit_item ? esc_attr($edit_item['version'] ?? '') : ''; ?>" placeholder="24.04"></div>
                            <div class="iso-field iso-form-full"><label>Açıklama</label><textarea name="desc" id="f_desc" rows="3" placeholder="Örn: 4.7 GB - Modern ve güvenli."><?php echo $edit_item ? esc_textarea($edit_item['desc'] ?? '') : ''; ?></textarea></div>
                            <div class="iso-field"><label>Resmi Web Sitesi</label><input type="url" name="website_url" id="f_website" value="<?php echo $edit_item ? esc_attr($edit_item['website_url'] ?? '') : ''; ?>" placeholder="https://ubuntu.com"></div>
                            <div class="iso-field"><div class="checkbox-wrap"><input type="checkbox" name="featured" id="f_featured" value="1" <?php echo ($edit_item && !empty($edit_item['featured'])) ? 'checked' : ''; ?>><span>📌 Öne Çıkan</span></div></div>
                        </div>
                    </div>

                    <div class="iso-section iso-sec-appearance">
                        <div class="iso-section-head"><i class="fa-solid fa-palette"></i><span>Görünüm</span></div>
                        <div class="iso-form-grid">
                            <div class="iso-field iso-form-full">
                                <label>Önizleme Görseli</label>
                                <div style="display:flex; gap:0.5rem;">
                                    <input type="url" name="image_url" id="f_image_url" value="<?php echo $edit_item ? esc_attr($edit_item['image_url'] ?? '') : ''; ?>" placeholder="Görsel URL veya seçin" style="flex:1">
                                    <button type="button" class="iso-img-upload-btn" style="padding:0 1rem;border-radius:0.5rem;border:1px solid #d1d5db;background:#f9fafb;cursor:pointer;font-size:0.85rem;"><i class="fa-solid fa-upload"></i></button>
                                </div>
                                <div id="iso-img-preview"><?php if($edit_item && !empty($edit_item['image_url'])): ?><img src="<?php echo esc_url($edit_item['image_url']); ?>" style="max-width:200px;border-radius:0.5rem;margin-top:0.5rem;"><?php endif; ?></div>
                            </div>
                            <div class="iso-field"><label>Kart Vurgu Rengi</label><input type="text" name="accent_color" class="iso-color-field" id="f_accent" value="<?php echo $edit_item && !empty($edit_item['accent_color']) ? esc_attr($edit_item['accent_color']) : '#2563eb'; ?>"></div>
                            <div class="iso-field"><label>Buton Zemin Rengi</label><input type="text" name="btn_bg_color" class="iso-color-field" id="f_btn_bg" value="<?php echo $edit_item && !empty($edit_item['btn_bg_color']) ? esc_attr($edit_item['btn_bg_color']) : '#059669'; ?>"></div>
                            <div class="iso-field"><label>Buton Yazı Rengi</label><input type="text" name="btn_txt_color" class="iso-color-field" id="f_btn_txt" value="<?php echo $edit_item && !empty($edit_item['btn_txt_color']) ? esc_attr($edit_item['btn_txt_color']) : '#ffffff'; ?>"></div>
                        </div>
                    </div>

                    <div class="iso-section iso-sec-hash">
                        <div class="iso-section-head"><i class="fa-solid fa-shield-halved"></i><span>Hash & Doğrulama</span></div>
                        <div class="iso-form-grid">
                            <div class="iso-field"><label>MD5SUM</label><input type="text" name="md5" id="f_md5" value="<?php echo $edit_item ? esc_attr($edit_item['md5'] ?? '') : ''; ?>"></div>
                            <div class="iso-field"><label>SHA1SUM</label><input type="text" name="sha1" id="f_sha1" value="<?php echo $edit_item ? esc_attr($edit_item['sha1'] ?? '') : ''; ?>"></div>
                            <div class="iso-field iso-form-full"><label>SHA256</label><input type="text" name="sha256" id="f_sha256" value="<?php echo $edit_item ? esc_attr($edit_item['sha256'] ?? '') : ''; ?>"></div>
                            <div class="iso-field iso-form-full">
                                <label>Doğrulama Komutları</label>
                                <textarea name="verification_content" id="f_verify_content" rows="4" placeholder="echo 'hash  dosya.iso' | sha256sum --check"><?php echo $edit_item ? esc_textarea($edit_item['verification_content'] ?? '') : ''; ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="iso-section iso-sec-mirror">
                        <div class="iso-section-head"><i class="fa-solid fa-globe"></i><span>İndirme Sunucuları</span></div>
                        <div class="iso-dynamic-rows" id="iso-mirror-rows">
                            <?php 
                            $edit_mirrors = ($edit_item && !empty($edit_item['mirrors'])) ? $edit_item['mirrors'] : array(array('country'=>'','url'=>''));
                            foreach ($edit_mirrors as $mirror): ?>
                            <div class="iso-dynamic-row">
                                <select name="mirror_country[]"><?php foreach ($countries as $code => $name): ?><option value="<?php echo esc_attr($code); ?>" <?php selected($mirror['country'] ?? '', $code); ?>><?php echo esc_html($name); ?></option><?php endforeach; ?></select>
                                <input type="url" name="mirror_url[]" value="<?php echo esc_attr($mirror['url'] ?? ''); ?>" placeholder="https://ftp.example.com/os.iso">
                                <button type="button" class="iso-row-btn iso-remove-row" onclick="isoRemoveRow(this)"><i class="fa-solid fa-xmark"></i></button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div style="margin-top:0.75rem"><button type="button" class="iso-row-btn iso-add-row" onclick="isoAddMirrorRow()"><i class="fa-solid fa-plus"></i></button></div>
                    </div>

                    <div class="iso-section iso-sec-torrent">
                        <div class="iso-section-head"><i class="fa-solid fa-magnet"></i><span>Torrent Bağlantıları</span></div>
                        <div class="iso-dynamic-rows" id="iso-torrent-rows">
                            <?php 
                            $edit_torrents = ($edit_item && isset($edit_item['torrents'])) ? $edit_item['torrents'] : array('');
                            foreach ($edit_torrents as $t): if(empty($t) && count($edit_torrents) > 1) continue; ?>
                            <div class="iso-dynamic-row">
                                <input type="text" name="torrents[]" value="<?php echo esc_attr($t); ?>" placeholder="magnet:?xt=urn:btih:...">
                                <button type="button" class="iso-row-btn iso-remove-row" onclick="isoRemoveRow(this)"><i class="fa-solid fa-xmark"></i></button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div style="margin-top:0.75rem"><button type="button" class="iso-row-btn iso-add-row" onclick="isoAddTorrentRow()"><i class="fa-solid fa-plus"></i></button></div>
                    </div>
                </div>
                <div class="iso-form-footer">
                    <button type="button" class="iso-cancel-btn" onclick="isoToggleForm(true)">İptal</button>
                    <button type="submit" class="iso-save-btn" id="iso-submit-btn"><i class="fa-solid fa-check"></i> Kaydet</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    (function($){
        var ajaxUrl = '<?php echo admin_url("admin-ajax.php"); ?>';
        var editData = <?php echo $edit_item ? json_encode($edit_item) : 'null'; ?>;
        var countriesOptsHtml = '<?php foreach(iso_get_countries() as $code => $name){ echo "<option value=\'".esc_attr($code)."\'>".esc_html($name)."</option>"; } ?>';

        $('#iso-visibility-toggle').on('change', function(){
            var isChecked = $(this).is(':checked');
            var security = '<?php echo wp_create_nonce("iso_dl_settings_nonce"); ?>';
            $.post(ajaxUrl, { action: 'iso_toggle_visibility', is_visible: isChecked, security: security }, function(res){
                if(res.success){
                    var label = isChecked ? 'Aktif' : 'Bakım Modunda';
                    $('.iso-toggle-label').text(label);
                }
            });
        });

        function isoClearForm() {
            document.getElementById('iso-form').reset();
            document.getElementById('iso_id_field').value = '';
            document.getElementById('iso-form-title').innerText = 'Yeni ISO Ekle';
            document.getElementById('iso-img-preview').innerHTML = '';
            if(jQuery.fn.wpColorPicker) {
                jQuery('#f_accent').wpColorPicker('color', '#2563eb');
                jQuery('#f_btn_bg').wpColorPicker('color', '#059669');
                jQuery('#f_btn_txt').wpColorPicker('color', '#ffffff');
            }
            document.getElementById('iso-mirror-rows').innerHTML = '<div class="iso-dynamic-row"><select name="mirror_country[]">'+countriesOptsHtml+'</select><input type="url" name="mirror_url[]" placeholder="https://ftp.example.com/os.iso"><button type="button" class="iso-row-btn iso-remove-row" onclick="isoRemoveRow(this)"><i class="fa-solid fa-xmark"></i></button></div>';
            document.getElementById('iso-torrent-rows').innerHTML = '<div class="iso-dynamic-row"><input type="text" name="torrents[]" placeholder="magnet:?xt=urn:btih:..."><button type="button" class="iso-row-btn iso-remove-row" onclick="isoRemoveRow(this)"><i class="fa-solid fa-xmark"></i></button></div>';
        }

        window.isoToggleForm = function(forceClose) {
            var p = document.getElementById('iso-form-panel');
            if (forceClose || p.classList.contains('open')) { p.classList.remove('open'); editData = null; } 
            else { isoClearForm(); p.classList.add('open'); setTimeout(function(){p.scrollIntoView({behavior:'smooth',block:'start'})},100); }
        };

        <?php if($edit_item): ?>
        document.addEventListener('DOMContentLoaded',function(){ var p=document.getElementById('iso-form-panel');p.classList.add('open'); setTimeout(function(){p.scrollIntoView({behavior:'smooth',block:'start'})},300); });
        <?php endif; ?>

        window.isoRemoveRow=function(btn){var row=btn.closest('.iso-dynamic-row'),c=row.parentElement;if(c.querySelectorAll('.iso-dynamic-row').length>1)row.remove()};
        window.isoAddMirrorRow=function(){var c=document.getElementById('iso-mirror-rows'),r=document.createElement('div');r.className='iso-dynamic-row';r.innerHTML='<select name="mirror_country[]">'+countriesOptsHtml+'</select><input type="url" name="mirror_url[]" placeholder="https://ftp.example.com/os.iso"><button type="button" class="iso-row-btn iso-remove-row" onclick="isoRemoveRow(this)"><i class="fa-solid fa-xmark"></i></button>';c.appendChild(r);r.querySelector('input').focus()};
        window.isoAddTorrentRow=function(){var c=document.getElementById('iso-torrent-rows'),r=document.createElement('div');r.className='iso-dynamic-row';r.innerHTML='<input type="text" name="torrents[]" placeholder="magnet:?xt=urn:btih:..."><button type="button" class="iso-row-btn iso-remove-row" onclick="isoRemoveRow(this)"><i class="fa-solid fa-xmark"></i></button>';c.appendChild(r);r.querySelector('input').focus()};

        $(document).ready(function(){ if(jQuery.fn.wpColorPicker) { $('.iso-color-field').wpColorPicker(); } });

        $(document).on('click', '.iso-img-upload-btn', function(e){
            e.preventDefault();
            wp.media({ title: 'ISO Görseli Seç', library : { type : 'image' }, button: { text: 'Görseli Kullan' }, multiple: false }).on('select', function() {
                var attachment = this.state().get('selection').first().toJSON(); $('#f_image_url').val(attachment.url); $('#iso-img-preview').html('<img src="'+attachment.url+'" style="max-width:200px;border-radius:0.5rem;margin-top:0.5rem;">');
            }).open();
        });

        $('#f_image_url').on('input', function(){ var url = $(this).val(); if(url) { $('#iso-img-preview').html('<img src="'+url+'" style="max-width:200px;border-radius:0.5rem;margin-top:0.5rem;">'); } else { $('#iso-img-preview').html(''); } });

        $('#iso-form').on('submit', function(e){
            e.preventDefault(); var btn = $('#iso-submit-btn'); btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Kaydediliyor...');
            $.post(ajaxUrl, $(this).serialize(), function(res){
                if(res.success){ $('#iso-saved-msg').fadeIn(); isoToggleForm(true); setTimeout(function(){ location.reload(); }, 1000); } else { alert('Hata oluştu!'); }
                btn.prop('disabled', false).html('<i class="fa-solid fa-check"></i> Kaydet');
            }).fail(function() { alert('Bağlantı hatası oluştu.'); btn.prop('disabled', false).html('<i class="fa-solid fa-check"></i> Kaydet'); });
        });

        window.isoEditItem=function(id){
            var items = <?php echo json_encode(get_option('iso_dl_items', array())); ?>; var item = items[id]; if(!item) return; editData = item;
            document.getElementById('iso-form-title').innerText = 'ISO Düzenle'; $('#iso_id_field').val(item.id); $('#f_title').val(item.title); $('#f_version').val(item.version); $('#f_desc').val(item.desc); $('#f_website').val(item.website_url || ''); $('#f_image_url').val(item.image_url || '');
            $('#iso-img-preview').html(item.image_url ? '<img src="'+item.image_url+'" style="max-width:200px;border-radius:0.5rem;margin-top:0.5rem;">' : '');
            $('#f_featured').prop('checked', item.featured); $('#f_md5').val(item.md5); $('#f_sha1').val(item.sha1); $('#f_sha256').val(item.sha256); $('#f_verify_content').val(item.verification_content || '');
            if(jQuery.fn.wpColorPicker) { $('#f_accent').wpColorPicker('color', item.accent_color || '#2563eb'); $('#f_btn_bg').wpColorPicker('color', item.btn_bg_color || '#059669'); $('#f_btn_txt').wpColorPicker('color', item.btn_txt_color || '#ffffff'); }
            document.getElementById('iso-mirror-rows').innerHTML = ''; var mc = item.mirrors.length ? item.mirrors : [{}]; mc.forEach(function(m){ window.isoAddMirrorRow(); var rows = document.querySelectorAll('#iso-mirror-rows .iso-dynamic-row'); var lastRow = rows[rows.length - 1]; lastRow.querySelector('select').value = m.country; lastRow.querySelector('input').value = m.url; });
            document.getElementById('iso-torrent-rows').innerHTML = ''; var tc = item.torrents && item.torrents.length ? item.torrents : ['']; tc.forEach(function(t){ window.isoAddTorrentRow(); var rows = document.querySelectorAll('#iso-torrent-rows .iso-dynamic-row'); var lastRow = rows[rows.length - 1]; lastRow.querySelector('input').value = t; });
            var p = document.getElementById('iso-form-panel'); if(!p.classList.contains('open')) p.classList.add('open'); setTimeout(function(){p.scrollIntoView({behavior:'smooth',block:'start'})},100);
        };
    })(jQuery);
    </script>
    <?php
}

/* ═══════════════════════════════════════════════════════
   ADMIN: İSTATİSTİK SAYFASI
   ═══════════════════════════════════════════════════════ */

function iso_dl_stats_page(): void {
    if (!current_user_can('manage_options')) return;
    $items = get_option('iso_dl_items', array()); $stats = get_option('iso_dl_stats', array());
    uasort($items, function($a, $b) use ($stats) { $a_feat=!empty($a['featured'])?1:0; $b_feat=!empty($b['featured'])?1:0; if($a_feat!==$b_feat) return $b_feat-$a_feat; $sa=isset($stats[$a['id']])?$stats[$a['id']]:0; $sb=isset($stats[$b['id']])?$stats[$b['id']]:0; return $sb-$sa; });
    ?>
    <div class="iso-admin-wrap"><div class="iso-admin-header"><div><h1><i class="fa-solid fa-chart-simple"></i> İndirme İstatistikleri</h1><p>ISO dosyalarınızın indirme sayılarını takip edin</p></div></div>
    <table class="iso-stats-table"><thead><tr><th>ISO Başlığı</th><th>Versiyon</th><th>İndirme Sayısı</th></tr></thead><tbody>
    <?php if(empty($items)): ?><tr><td colspan="3" style="text-align:center;color:#94a3b8;padding:40px">Henüz veri yok</td></tr>
    <?php else: foreach($items as $item): $cnt=isset($stats[$item['id']])?$stats[$item['id']]:0; ?>
    <tr><td><strong><?php echo esc_html($item['title']); ?></strong></td><td>v<?php echo esc_html($item['version']); ?></td><td><span class="iso-stats-num"><?php echo number_format_i18n($cnt); ?></span></td></tr><?php endforeach; endif; ?>
    </tbody></table></div><?php
}

/* ═══════════════════════════════════════════════════════
   ÖN YÜZ
   ═══════════════════════════════════════════════════════ */

add_action('wp_enqueue_scripts', 'iso_enqueue_assets');
function iso_enqueue_assets(): void {
    wp_enqueue_style('iso-gfonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap', array(), null);
    wp_enqueue_style('iso-fa', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css', array(), '6.5.1');
}

add_shortcode('downloads', 'iso_render_page');

function iso_render_page($atts): string {
    static $rendered = false;
    if ($rendered) return '';
    $rendered = true;

    $is_visible = get_option('iso_dl_visible', 1);
    if (!$is_visible) {
        return '<div style="display:flex;align-items:center;justify-content:center;min-height:60vh;text-align:center;padding:40px 20px;font-family:system-ui,sans-serif">
            <div><div style="font-size:48px;margin-bottom:20px;color:#94a3b8"><i class="fa-solid fa-eye-slash"></i></div>
            <h2 style="font-size:22px;color:#475569;margin-bottom:8px">Bu sayfa şu anda bakımdadır</h2>
            <p style="color:#94a3b8">Lütfen daha sonra tekrar kontrol edin.</p></div></div>';
    }

    $items = get_option('iso_dl_items', array());
    if(empty($items)) return '<p style="text-align:center;padding:60px 20px;font-size:16px;color:#666;">Henüz yayınlanmış ISO dosyası bulunmuyor.</p>';

    uasort($items, function($a, $b) { $a_feat=!empty($a['featured'])?1:0; $b_feat=!empty($b['featured'])?1:0; if($a_feat!==$b_feat) return $b_feat-$a_feat; return version_compare($b['version'], $a['version']); });
    $flag_map = iso_get_flag_map(); $ajax_url = admin_url('admin-ajax.php');
    ob_start();
    ?>

    <style>
        :root {
            --bg: #f8fafc;
            --card-bg: #ffffff;
            --border: #e2e8f0;
            --text: #0f172a;
            --text-secondary: #475569;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow: 0 1px 3px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -2px rgba(0,0,0,0.1);
            --radius: 1rem;
            --radius-lg: 1.25rem;
            --transition: 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .iso-page, .iso-page * { box-sizing: border-box; margin: 0; padding: 0; }
body.page .entry-title, body.page .page-title, body.page h1.entry-title,
body.page .entry-header, body.page .page-header {
    display: none !important;
    margin: 0 !important;
    padding: 0 !important;
}
body.page article .entry-content {
    margin: 0 !important;
    padding: 0 !important;
    max-width: 100% !important;
}
        .iso-page {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text);
            width: 100%;
            max-width: 100%;
            margin: 0 auto;
            overflow-x: hidden;
            min-height: 100vh;
            padding: 2rem 1rem;
        }
        .iso-container {
            max-width: 960px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }
        .iso-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
        }
        .iso-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }
        .iso-card.featured {
            border-left: 4px solid var(--iso-accent);
        }
        .iso-card-head {
            padding: 1.25rem 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }
        .iso-card-logo {
            width: 52px; height: 52px;
            border-radius: 0.875rem;
            background: color-mix(in srgb, var(--iso-accent) 12%, transparent);
            color: var(--iso-accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            flex-shrink: 0;
        }
        .iso-card-logo img { width: 28px; height: 28px; object-fit: contain; }
        .iso-card-info { flex: 1; min-width: 0; }
        .iso-card-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text);
            line-height: 1.3;
        }
        .iso-card-meta {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .iso-featured-tag {
            font-size: 0.7rem;
            background: var(--iso-accent);
            color: #fff;
            padding: 0.25rem 0.75rem;
            border-radius: 100px;
            font-weight: 700;
            letter-spacing: 0.3px;
            flex-shrink: 0;
            margin-top: 0.15rem;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }
        .iso-card-img {
            width: 100%;
            max-height: 240px;
            overflow: hidden;
            background: #f1f5f9;
        }
        .iso-card-img img { width: 100%; height: 100%; object-fit: cover; }
        .iso-card-desc {
            padding: 0 1.5rem 0.5rem;
            font-size: 0.9rem;
            color: #334155;
            line-height: 1.6;
        }
        .iso-card-desc a { color: var(--iso-accent); text-decoration: underline; font-weight: 500; }
        .iso-dl-btn, .iso-verify-btn, .iso-website-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.25rem;
            border-radius: 0.75rem;
            font-family: 'Inter', system-ui, sans-serif;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: var(--transition);
            border: none;
            line-height: 1.4;
        }
        .iso-dl-btn {
            background: var(--iso-btn-bg);
            color: var(--iso-btn-txt);
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        .iso-dl-btn:hover { filter: brightness(1.1); transform: translateY(-1px); }
        .iso-verify-btn {
            background: transparent;
            border: 1.5px solid var(--iso-accent);
            color: var(--iso-accent);
        }
        .iso-verify-btn:hover { background: color-mix(in srgb, var(--iso-accent) 10%, transparent); }
        .iso-website-btn {
            background: #fff;
            border: 1px solid #cbd5e1;
            color: #334155;
        }
        .iso-website-btn:hover { background: #f8fafc; border-color: #94a3b8; }
        .iso-card-footer {
            padding: 0.75rem 1.5rem 1.25rem;
            display: flex;
            gap: 0.6rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .iso-accordion { border-top: 1px solid #f1f5f9; }
        .iso-acc-btn {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.9rem 1.5rem;
            background: none;
            border: none;
            cursor: pointer;
            font-family: 'Inter', system-ui, sans-serif;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text);
            transition: background 0.15s;
        }
        .iso-acc-btn:hover { background: #f8fafc; }
        .iso-acc-btn i.ico { font-size: 1rem; width: 22px; text-align: center; color: var(--iso-accent); }
        .iso-acc-btn .arrow { margin-left: auto; font-size: 0.8rem; color: #94a3b8; transition: transform 0.25s ease; }
        .iso-acc-btn.open .arrow { transform: rotate(180deg); }
        .iso-acc-body { max-height: 0; overflow: hidden; transition: max-height 0.35s ease; }
        .iso-acc-body-inner { padding: 0 1.5rem 1.25rem; }
        .iso-mirror-list { display: flex; flex-direction: column; gap: 0.5rem; }
        .iso-mirror-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text);
            transition: var(--transition);
        }
        .iso-mirror-link:hover {
            background: color-mix(in srgb, var(--iso-accent) 6%, white);
            border-color: var(--iso-accent);
            color: var(--iso-accent);
        }
        .iso-mirror-flag { font-size: 1.25rem; }
        .iso-mirror-name { flex: 1; }
        .iso-mirror-dl { font-size: 0.8rem; color: #94a3b8; }
        .iso-hash-row {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.7rem 0.9rem;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            margin-bottom: 0.5rem;
        }
        .iso-hash-label {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.75rem;
            font-weight: 600;
            color: #d97706;
            width: 60px;
            flex-shrink: 0;
        }
        .iso-hash-val {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.8rem;
            color: var(--text);
            word-break: break-all;
            flex: 1;
            user-select: all;
        }
        .iso-copy-btn {
            width: 30px; height: 30px;
            border-radius: 0.5rem;
            border: 1px solid #e2e8f0;
            background: #fff;
            color: #94a3b8;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            flex-shrink: 0;
            transition: var(--transition);
        }
        .iso-copy-btn:hover { color: var(--iso-accent); border-color: var(--iso-accent); }
        .iso-copy-btn.ok { background: color-mix(in srgb, var(--iso-accent) 10%, white); color: var(--iso-accent); border-color: var(--iso-accent); }
        .iso-torrent-list { display: flex; flex-direction: column; gap: 0.5rem; }
        .iso-torrent-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            background: #faf5ff;
            border: 1px solid #e9d5ff;
            border-radius: 0.75rem;
            text-decoration: none;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.8rem;
            font-weight: 600;
            color: #581c87;
            transition: var(--transition);
        }
        .iso-torrent-link:hover { background: #f3e8ff; border-color: #c084fc; }
        /* VERIFY MODAL */
        .iso-verify-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.7); backdrop-filter: blur(8px);
            z-index: 999999; display: flex; align-items: center; justify-content: center;
            padding: 1.5rem; opacity: 0; visibility: hidden; transition: 0.3s ease;
        }
        .iso-verify-overlay.open { opacity: 1; visibility: visible; }
        .iso-verify-box {
            background: #0d1117; border: 1px solid #30363d; border-radius: 1rem;
            max-width: 700px; width: 100%; max-height: 85vh;
            display: flex; flex-direction: column;
            transform: translateY(20px) scale(0.98); transition: 0.3s ease;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
        }
        .iso-verify-overlay.open .iso-verify-box { transform: translateY(0) scale(1); }
        .iso-verify-header {
            padding: 1.25rem 1.5rem; border-bottom: 1px solid #21262d;
            display: flex; align-items: center; justify-content: space-between;
        }
        .iso-verify-header h3 { font-size: 1.05rem; font-weight: 700; color: #e6edf3; display: flex; align-items: center; gap: 0.5rem; }
        .iso-verify-header h3 i { color: #58a6ff; }
        .iso-verify-close {
            width: 32px; height: 32px; border-radius: 0.5rem;
            border: 1px solid #30363d; background: #161b22; color: #8b949e;
            cursor: pointer; font-size: 0.9rem; transition: var(--transition);
        }
        .iso-verify-close:hover { background: #21262d; color: #f85149; border-color: #f85149; }
        .iso-verify-body { flex: 1; overflow-y: auto; padding: 1.5rem; }
        .iso-terminal {
            background: #161b22; border: 1px solid #30363d; border-radius: 0.75rem;
            padding: 1.25rem; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word;
            font-family: 'JetBrains Mono', monospace; font-size: 0.85rem; color: #c9d1d9;
            line-height: 1.7; margin-bottom: 1.25rem;
        }
        .iso-verify-actions { display: flex; gap: 0.75rem; flex-wrap: wrap; }
        .iso-term-copy-btn, .iso-term-close-btn {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.6rem 1.25rem; border-radius: 0.75rem;
            font-size: 0.875rem; font-weight: 600; cursor: pointer;
            font-family: 'Inter', sans-serif; transition: var(--transition); border: none;
        }
        .iso-term-copy-btn { background: #238636; color: #fff; }
        .iso-term-copy-btn:hover { background: #2ea043; }
        .iso-term-close-btn { background: #21262d; color: #c9d1d9; border: 1px solid #30363d; }
        .iso-term-close-btn:hover { background: #30363d; color: #e6edf3; }
        /* TOAST */
        .iso-toast-box { position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 1000000; display: flex; flex-direction: column; gap: 0.5rem; pointer-events: none; }
        .iso-toast {
            display: flex; align-items: center; gap: 0.6rem;
            padding: 0.85rem 1.25rem; background: #fff; border: 1px solid #bbf7d0;
            border-radius: 0.75rem; box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transform: translateX(120%); transition: transform 0.3s cubic-bezier(0.4,0,0.2,1);
            pointer-events: auto; font-size: 0.875rem; font-weight: 600; color: #065f46;
        }
        .iso-toast.show { transform: translateX(0); }
        .iso-toast i { color: #059669; font-size: 1.1rem; }
        @media (max-width: 640px) {
            .iso-page { padding: 1rem 0.75rem; }
            .iso-container { gap: 1rem; }
            .iso-card-head { padding: 1rem 1.25rem; gap: 0.75rem; }
            .iso-card-logo { width: 44px; height: 44px; border-radius: 0.75rem; font-size: 1.2rem; }
            .iso-card-desc { padding: 0 1.25rem 0.5rem; }
            .iso-card-footer { padding: 0.5rem 1.25rem 1rem; }
            .iso-acc-btn { padding: 0.75rem 1.25rem; font-size: 0.85rem; }
            .iso-acc-body-inner { padding: 0 1.25rem 1rem; }
            .iso-mirror-link { padding: 0.65rem 0.85rem; font-size: 0.85rem; }
            .iso-hash-val { font-size: 0.75rem; }
            .iso-verify-body { padding: 1rem; }
            .iso-terminal { padding: 1rem; font-size: 0.8rem; }
        }
    </style>

    <div class="iso-page">
        <div class="iso-container">
            <?php foreach($items as $item):
                $mc = count($item['mirrors']); $tc = isset($item['torrents']) ? count($item['torrents']) : 0; $first_url = $mc > 0 ? $item['mirrors'][0]['url'] : '#';
                $has_hash = !empty($item['md5']) || !empty($item['sha1']) || !empty($item['sha256']); $accent = !empty($item['accent_color']) ? esc_attr($item['accent_color']) : '#2563eb';
                $btn_bg = !empty($item['btn_bg_color']) ? esc_attr($item['btn_bg_color']) : '#059669'; $btn_txt = !empty($item['btn_txt_color']) ? esc_attr($item['btn_txt_color']) : '#ffffff';
                $has_verify = !empty($item['verification_content']); $has_website = !empty($item['website_url']); $domain = '';
                if($has_website) { $parsed = wp_parse_url($item['website_url']); if(isset($parsed['host'])) $domain = $parsed['host']; }
            ?>
            <div class="iso-card<?php echo !empty($item['featured']) ? ' featured' : ''; ?>" data-id="<?php echo esc_attr($item['id']); ?>" style="--iso-accent: <?php echo $accent; ?>; --iso-btn-bg: <?php echo $btn_bg; ?>; --iso-btn-txt: <?php echo $btn_txt; ?>;">
                <div class="iso-card-head">
                    <div class="iso-card-logo">
                        <?php if(!empty($domain)): ?><img src="https://www.google.com/s2/favicons?domain=<?php echo esc_attr($domain); ?>&sz=64" alt="" onerror="this.parentNode.innerHTML='<i class=\'fa-solid fa-compact-disc\'></i>';"><?php else: ?><i class="fa-solid fa-compact-disc"></i><?php endif; ?>
                    </div>
                    <div class="iso-card-info">
                        <div class="iso-card-title"><?php echo esc_html($item['title']); ?></div>
                        <div class="iso-card-meta">
                            <span><i class="fa-solid fa-code-branch"></i> v<?php echo esc_html($item['version']); ?></span>
                            <span><i class="fa-solid fa-server"></i> <?php echo $mc; ?> sunucu</span>
                            <?php if($tc > 0): ?><span><i class="fa-solid fa-magnet"></i> <?php echo $tc; ?> torrent</span><?php endif; ?>
                        </div>
                    </div>
                    <?php if(!empty($item['featured'])): ?><span class="iso-featured-tag"><i class="fa-solid fa-thumbtack"></i> Öne Çıkan</span><?php endif; ?>
                </div>
                <?php if(!empty($item['image_url'])): ?><div class="iso-card-img"><img src="<?php echo esc_url($item['image_url']); ?>" alt="<?php echo esc_attr($item['title']); ?>"></div><?php endif; ?>
                <?php if(!empty($item['desc'])): ?><div class="iso-card-desc"><?php echo wp_kses_post($item['desc']); ?></div><?php endif; ?>
                <div class="iso-card-footer">
                    <a href="<?php echo esc_url($first_url); ?>" class="iso-dl-btn iso-track-click"><i class="fa-solid fa-download"></i> İndir</a>
                    <?php if($has_verify): ?><button onclick="isoOpenVerifyModal('<?php echo esc_js($item['id']); ?>')" class="iso-verify-btn"><i class="fa-solid fa-shield-halved"></i> Doğrula</button><?php endif; ?>
                    <?php if($has_website): ?><a href="<?php echo esc_url($item['website_url']); ?>" class="iso-website-btn" target="_blank" rel="noopener"><i class="fa-solid fa-globe"></i> Site</a><?php endif; ?>
                </div>
                <?php if($has_hash): ?><div class="iso-accordion"><button class="iso-acc-btn" onclick="isoToggle(this)" aria-expanded="false"><i class="fa-solid fa-fingerprint ico"></i> Hash Doğrulama<i class="fa-solid fa-chevron-down arrow"></i></button><div class="iso-acc-body"><div class="iso-acc-body-inner">
                    <?php if(!empty($item['sha256'])): ?><div class="iso-hash-row"><span class="iso-hash-label">SHA256</span><span class="iso-hash-val"><?php echo esc_html($item['sha256']); ?></span><button class="iso-copy-btn" onclick="isoCopy(this,'<?php echo esc_js($item['sha256']); ?>','SHA256')"><i class="fa-regular fa-copy"></i></button></div><?php endif; ?>
                    <?php if(!empty($item['sha1'])): ?><div class="iso-hash-row"><span class="iso-hash-label">SHA1</span><span class="iso-hash-val"><?php echo esc_html($item['sha1']); ?></span><button class="iso-copy-btn" onclick="isoCopy(this,'<?php echo esc_js($item['sha1']); ?>','SHA1')"><i class="fa-regular fa-copy"></i></button></div><?php endif; ?>
                    <?php if(!empty($item['md5'])): ?><div class="iso-hash-row"><span class="iso-hash-label">MD5</span><span class="iso-hash-val"><?php echo esc_html($item['md5']); ?></span><button class="iso-copy-btn" onclick="isoCopy(this,'<?php echo esc_js($item['md5']); ?>','MD5')"><i class="fa-regular fa-copy"></i></button></div><?php endif; ?>
                </div></div></div><?php endif; ?>
                <?php if($mc > 0): ?><div class="iso-accordion"><button class="iso-acc-btn" onclick="isoToggle(this)" aria-expanded="false"><i class="fa-solid fa-globe ico"></i> İndirme Sunucuları (<?php echo $mc; ?>)<i class="fa-solid fa-chevron-down arrow"></i></button><div class="iso-acc-body"><div class="iso-acc-body-inner"><div class="iso-mirror-list">
                    <?php foreach($item['mirrors'] as $m): $fl = isset($flag_map[$m['country']]) ? $flag_map[$m['country']] : '🌐'; ?>
                    <a href="<?php echo esc_url($m['url']); ?>" class="iso-mirror-link iso-track-click" target="_blank" rel="noopener"><span class="iso-mirror-flag"><?php echo $fl; ?></span><span class="iso-mirror-name"><?php echo esc_html($m['country']); ?></span><span class="iso-mirror-dl"><i class="fa-solid fa-arrow-down"></i></span></a>
                    <?php endforeach; ?>
                </div></div></div></div><?php endif; ?>
                <?php if($tc > 0): ?><div class="iso-accordion"><button class="iso-acc-btn" onclick="isoToggle(this)" aria-expanded="false"><i class="fa-solid fa-magnet ico"></i> Torrent (<?php echo $tc; ?>)<i class="fa-solid fa-chevron-down arrow"></i></button><div class="iso-acc-body"><div class="iso-acc-body-inner"><div class="iso-torrent-list">
                    <?php foreach($item['torrents'] as $t): ?><a href="<?php echo esc_url($t); ?>" class="iso-torrent-link iso-track-click" target="_blank" rel="noopener"><i class="fa-solid fa-magnet"></i><span class="iso-torrent-text"><?php echo esc_html($t); ?></span></a><?php endforeach; ?>
                </div></div></div></div><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div id="iso-verify-modal" class="iso-verify-overlay"><div class="iso-verify-box"><div class="iso-verify-header"><h3><i class="fa-solid fa-terminal"></i> Doğrulama Terminali</h3><button onclick="isoCloseVerifyModal()" class="iso-verify-close"><i class="fa-solid fa-xmark"></i></button></div><div class="iso-verify-body"><div class="iso-terminal" id="iso-verify-code"></div><div class="iso-verify-actions"><button onclick="isoCopyVerifyContent()" class="iso-term-copy-btn"><i class="fa-regular fa-copy"></i> Komutları Kopyala</button><button onclick="isoCloseVerifyModal()" class="iso-term-close-btn"><i class="fa-solid fa-xmark"></i> Kapat</button></div></div></div></div>
    <div class="iso-toast-box" id="iso-toast-box"></div>

    <script>
    (function(){
        var ajaxUrl='<?php echo esc_url($ajax_url); ?>';var isoVerifyData=<?php echo json_encode(array_column($items,'verification_content','id'),JSON_UNESCAPED_UNICODE); ?>;var currentVerifyId=null;
        window.isoOpenVerifyModal=function(id){if(!isoVerifyData[id])return;currentVerifyId=id;var modal=document.getElementById('iso-verify-modal');document.getElementById('iso-verify-code').textContent=isoVerifyData[id];modal.classList.add('open');document.body.style.overflow='hidden'};
        window.isoCloseVerifyModal=function(){var modal=document.getElementById('iso-verify-modal');modal.classList.remove('open');document.body.style.overflow='';currentVerifyId=null};
        document.getElementById('iso-verify-modal').addEventListener('click',function(e){if(e.target===this)isoCloseVerifyModal()});
        window.isoCopyVerifyContent=function(){if(!currentVerifyId||!isoVerifyData[currentVerifyId])return;navigator.clipboard.writeText(isoVerifyData[currentVerifyId]).then(function(){isoToast('Doğrulama komutları panoya kopyalandı!')})};
        window.isoToggle=function(btn){var open=btn.classList.toggle('open');btn.setAttribute('aria-expanded',open);var body=btn.nextElementSibling;if(open){body.style.maxHeight=body.scrollHeight+'px'}else{body.style.maxHeight='0'}};
        window.isoCopy=function(btn,text,label){navigator.clipboard.writeText(text).then(function(){btn.classList.add('ok');btn.innerHTML='<i class="fa-solid fa-check"></i>';isoToast(label+' panoya kopyalandı');setTimeout(function(){btn.classList.remove('ok');btn.innerHTML='<i class="fa-regular fa-copy"></i>'},2000)})};
        function isoToast(msg){var box=document.getElementById('iso-toast-box'),t=document.createElement('div');t.className='iso-toast';t.innerHTML='<i class="fa-solid fa-circle-check"></i> '+msg;box.appendChild(t);requestAnimationFrame(function(){t.classList.add('show')});setTimeout(function(){t.classList.remove('show');setTimeout(function(){t.remove()},350)},2500)}
        document.addEventListener('click',function(e){var link=e.target.closest('.iso-track-click');if(link){var card=link.closest('.iso-card');if(card){var isoId=card.dataset.id;if(isoId&&navigator.sendBeacon){var formData=new FormData();formData.append('action','iso_track_dl');formData.append('iso_id',isoId);navigator.sendBeacon(ajaxUrl,formData)}}}})
    })();
    </script>
    <?php
    return ob_get_clean();
}
