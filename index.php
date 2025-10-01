<?php
// 開発用
ini_set('display_errors', 1);
error_reporting(E_ALL);
//初期設定
require_once __DIR__ . '/path.php';
require_once __DIR__ . '/functions/init_function.php';
require_once __DIR__ . '/functions/helper_function.php';
//認証
require_once __DIR__ . '/functions/auth_function.php';
check_authentication();

require_once __DIR__ . '/functions/get_request_function.php';
require_once __DIR__ . '/functions/post_request_function.php';

// --- 画面 ---
$current_path_raw = $_GET['path'] ?? ''; $current_path = realpath(DATA_ROOT . '/' . $current_path_raw);
if ($current_path === false || strpos($current_path, DATA_ROOT) !== 0) $current_path = DATA_ROOT;
$web_path = ltrim(substr($current_path, strlen(DATA_ROOT)), '/'); $web_path = str_replace('\\', '/', $web_path);
$items = [];
$all_items = array_diff(scandir($current_path), ['.', '..']);
natsort($all_items);

foreach ($all_items as $item) { 
    if (str_starts_with($item, '.')) continue;
    $item_path = $current_path . '/' . $item;
    $is_dir = is_dir($item_path);
    $size = $is_dir ? null : filesize($item_path);
    $items[] = [
        'name' => $item, 
        'is_dir' => $is_dir,
        'size' => $size,
        'formatted_size' => format_bytes($size)
    ]; 
}
usort($items, fn($a, $b) => ($a['is_dir'] !== $b['is_dir']) ? ($a['is_dir'] ? -1 : 1) : strcasecmp($a['name'], $b['name']));
$sidebar_folders = get_directory_tree(DATA_ROOT);
$all_dirs = get_all_directories_recursive(DATA_ROOT); sort($all_dirs);
$breadcrumbs = []; 
if (!empty($web_path)) { 
    $tmp_path = ''; 
    foreach (explode('/', $web_path) as $part) { 
        $tmp_path .= (empty($tmp_path) ? '' : '/') . $part; 
        $breadcrumbs[] = ['name' => $part, 'path' => $tmp_path]; 
    } 
}
$message = $_SESSION['message'] ?? null; unset($_SESSION['message']);
$json_message = json_encode($message);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>One Storage</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        .sidebar { 
            position: fixed; top: 0; bottom: 0; left: 0; z-index: 100; box-shadow: inset -1px 0 0 rgba(0, 0, 0, 1);
            background-color: #f8f9fa !important;
        }
        .sidebar-sticky { overflow-y: auto; }
        .nav-link.active { color: #000000ff !important; } 
        .sidebar .nav-link { padding-top: 0.25rem; padding-bottom: 0.25rem; }
        .toggle-icon { display: inline-block; width: 1rem; text-align: center; font-weight: bold; }
        .sidebar .nav-link:not(.active):not(.is-active) {color: #000000 !important;}
        .sidebar .nav-item-folder { position: relative; }
        .sidebar .nav-item-folder .nav-link.is-active { color: #fff !important; background-color: #0d6efd; font-weight: 600; }
        .sidebar .nav-item-folder .nav-link.is-active .bi-folder { color: #fff !important; }
        .sidebar .nav-item-folder.is-active-parent > .nav-link, .sidebar .nav-item-folder.is-active-parent > .nav-link .bi-folder { color: #000000ff !important; }
        .toast-container { position: fixed; bottom: 1rem; right: 1rem; z-index: 1090; }
        
        .file-list .file-row:hover { background-color: #f8f9fa; }
        .file-list a { text-decoration: none; color: inherit; }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/templates/nav.php'; ?>

<div class="container-fluid">
    <div class="row">
        <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-ligth sidebar collapse">
            <div class="sidebar-sticky pt-3">
                <ul class="nav flex-column px-3">
                    <li class="nav-item"><a class="nav-link <?= (empty($web_path)) ? 'active' : '' ?>" href="?path="><i class="bi bi-house-door me-2"></i>ルート</a></li>
                </ul>
                <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted text-uppercase"><span>フォルダ</span></h6>
                <ul class="nav flex-column mb-2">
                    <?php 
                    function render_folder_tree($folders, $current_path, $level = 0) {
                        $html = ''; $indent_px = 16 * ($level + 1);
                        foreach ($folders as $folder) {
                            $has_children = !empty($folder['children']);
                            $is_active = ($current_path === $folder['path']);
                            $is_active_parent = str_starts_with($current_path, $folder['path'] . '/');
                            $li_classes = 'nav-item nav-item-folder' . ($is_active_parent ? ' is-active-parent' : '');
                            $link_classes = 'nav-link d-flex align-items-center' . ($is_active ? ' is-active' : '');
                            $collapse_id = 'collapse-' . str_replace('/', '-', $folder['path']);
                            $is_collapsed_open = $is_active || $is_active_parent;

                            $html .= '<li class="' . $li_classes . '">';
                            $html .= '<a class="' . $link_classes . '" href="?path=' . urlencode($folder['path']) . '" style="padding-left: ' . $indent_px . 'px;">';
                            if ($has_children) {
                                $html .= '<i class="bi me-1 toggle-icon" data-bs-toggle="collapse" data-bs-target="#' . $collapse_id . '" aria-expanded="' . ($is_collapsed_open ? 'true' : 'false') . '" style="cursor: pointer;">' . ($is_collapsed_open ? '▾' : '▸') . '</i>';
                            } else {
                                $html .= '<i class="me-1" style="width: 1rem;"></i>';
                            }
                            $html .= '<i class="bi bi-folder me-2"></i>' . htmlspecialchars($folder['name'], ENT_QUOTES, 'UTF-8') . '</a>';
                            if ($has_children) {
                                $html .= '<div class="collapse ' . ($is_collapsed_open ? 'show' : '') . '" id="' . $collapse_id . '"><ul class="nav flex-column">' . render_folder_tree($folder['children'], $current_path, $level + 1) . '</ul></div>';
                            }
                            $html .= '</li>';
                        }
                        return $html;
                    }
                    echo render_folder_tree($sidebar_folders, $web_path);
                    ?>
                </ul>
            </div>
        </nav>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div id="breadcrumbContainer" class="flex-grow-1">
                    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="?path=" class="text-dark text-decoration-none">ルート</a></li>
                    <?php foreach ($breadcrumbs as $crumb): ?>
                        <li class="breadcrumb-item">
                            <a href="?path=<?= urlencode($crumb['path']) ?>" class="text-dark text-decoration-none">
                                <?= htmlspecialchars($crumb['name'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </li>
                    <?php endforeach; ?></ol></nav>
                </div>
                <div id="tableActionsContainer" class="d-none">
                    <span class="text-muted me-3"><strong id="selectionCount">0</strong>個選択中</span>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#moveItemsModal"><i class="bi bi-folder-symlink"></i> 選択項目を移動</button>
                </div>
            </div>
            
            <div class="file-list">
                <div class="row gx-2 text-muted border-bottom py-2 d-none d-md-flex small">
                    <div class="col-auto">
                        <input class="form-check-input" type="checkbox" id="selectAllCheckbox">
                    </div>
                    <div class="col fw-bold">ファイル名</div>
                    <div class="col-3 col-lg-2 text-end fw-bold">容量</div>
                    <div class="col-auto" style="width: 50px;"></div>
                </div>

                <?php if (empty($items)): ?>
                    <div class="text-center text-muted py-5">ファイルがありません</div>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <div class="row gx-2 d-flex align-items-center border-bottom file-row">
                            <div class="col-auto py-2">
                                <input class="form-check-input item-checkbox" type="checkbox" value="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="col text-truncate py-2">
                                <?php if ($item['is_dir']): ?>
                                    <a href="?path=<?= urlencode(ltrim($web_path . '/' . $item['name'], '/')) ?>" class="d-flex align-items-center">
                                        <i class="bi bi-folder-fill text-primary me-2 fs-5"></i>
                                        <span><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </a>
                                <?php else: ?>
                                    <a href="?action=view&path=<?= urlencode(ltrim($web_path . '/' . $item['name'], '/')) ?>" target="_blank" class="d-flex align-items-center">
                                        <i class="bi bi-file-earmark-text me-2 fs-5"></i>
                                        <span><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="col-3 col-lg-2 text-end py-2">
                                <?= htmlspecialchars($item['formatted_size'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <div class="col-auto py-2">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="アクション">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#renameItemModal" data-bs-item-name="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>" data-bs-is-dir="<?= $item['is_dir'] ? '1' : '0' ?>"><i class="bi bi-pencil-fill me-2"></i>名前の変更</button></li>
                                        <?php if (!$item['is_dir']): ?>
                                        <li><a class="dropdown-item" href="?action=download&path=<?= urlencode(ltrim($web_path . '/' . $item['name'], '/')) ?>"><i class="bi bi-download me-2"></i>ダウンロード</a></li>
                                        <?php endif; ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form action="index.php" method="post" onsubmit="return confirm('本当に「<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>」を削除しますか？\nこの操作は元に戻せません。');">
                                                <input type="hidden" name="action" value="delete_item">
                                                <input type="hidden" name="path" value="<?= htmlspecialchars($web_path, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="item_name" value="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>">
                                                <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash-fill me-2"></i>削除</button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            </main>
    </div>
</div>

<div class="toast-container"></div>

<div class="modal fade" id="createFolderModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">新しいフォルダの作成</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form action="index.php" method="post"><div class="modal-body"><input type="hidden" name="action" value="create_folder"><input type="hidden" name="path" value="<?= htmlspecialchars($web_path, ENT_QUOTES, 'UTF-8') ?>"><div class="mb-3"><label for="folder_name" class="form-label">フォルダ名</label><input type="text" class="form-control" id="folder_name" name="folder_name" required></div><div class="form-text">`.`で始まるフォルダ名や、特殊な記号は使えません。</div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button><button type="submit" class="btn btn-primary">作成</button></div></form></div></div></div>

<div class="modal fade" id="uploadFileModal" tabindex="-1" data-bs-backdrop="true" data-bs-keyboard="true"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">ファイルのアップロード</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal" id="uploadModalCloseBtn"></button>
</div>
<form id="uploadFileForm" action="index.php" method="post" enctype="multipart/form-data"><div class="modal-body">
    <input type="hidden" name="action" value="upload_file">
    <input type="hidden" name="path" value="<?= htmlspecialchars($web_path, ENT_QUOTES, 'UTF-8') ?>">
    <div class="mb-3">
        <label for="files" class="form-label">アップロードするファイルを選択 (複数可)</label>
        <input class="form-control" type="file" id="files" name="files[]" multiple required accept="<?= htmlspecialchars($accept_attribute, ENT_QUOTES, 'UTF-8') ?>">
    </div>
    <div class="form-text">
        アップロード先: /<?= htmlspecialchars(empty($web_path) ? '' : $web_path . '/', ENT_QUOTES, 'UTF-8') ?><br>
        <?= !empty($file_config['allowed_extensions']) ? '許可する拡張子: ' . htmlspecialchars(implode(', ', $file_config['allowed_extensions']), ENT_QUOTES, 'UTF-8') . '<br>' : '' ?>
        ファイルサイズ上限: <?= htmlspecialchars($file_config['max_file_size_mb'], ENT_QUOTES, 'UTF-8') ?> MB<br>
        `.`で始まるファイル名はアップロードできません。
    </div>
</div><div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="uploadModalFooterCloseBtn">閉じる</button>
    <button type="submit" id="uploadSubmitBtn" class="btn btn-primary">アップロード</button>
</div></form>
</div></div></div>

<div class="modal fade" id="renameItemModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="renameItemModalLabel">名前の変更</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form action="index.php" method="post"><div class="modal-body"><input type="hidden" name="action" value="rename_item"><input type="hidden" name="path" value="<?= htmlspecialchars($web_path, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="old_name" id="rename_old_name"><div class="mb-3"><label for="rename_new_name" class="form-label">新しい名前</label><div class="input-group"><input type="text" class="form-control" id="rename_new_name" name="new_name" required><span class="input-group-text" id="rename_extension">.ext</span></div><div class="form-text" id="rename_help_text">記号や`.`で始まる名前は使えません。</div></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button><button type="submit" class="btn btn-primary">変更を保存</button></div></form></div></div></div>
<div class="modal fade" id="moveItemsModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">アイテムの移動</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form action="index.php" method="post"><div class="modal-body"><input type="hidden" name="action" value="move_items"><input type="hidden" name="path" value="<?= htmlspecialchars($web_path, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="items_json" id="move_items_json"><div class="mb-3"><label for="destination" class="form-label">移動先のフォルダを選択</label><select class="form-select" name="destination" id="destination" required><option value="">ルートフォルダ</option><?php foreach($all_dirs as $dir): ?><option value="<?= htmlspecialchars($dir, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($dir, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button><button type="submit" class="btn btn-primary">ここに移動</button></div></form></div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const phpMessage = <?= $json_message ?>;

    function showToast(type, message) {
        const toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) return;
        const bgColor = {'success': 'text-bg-success', 'danger': 'text-bg-danger', 'warning': 'text-bg-warning', 'info': 'text-bg-primary'}[type] || 'text-bg-primary';
        const iconHtml = {'success': '<i class="bi bi-check-circle-fill me-2"></i>', 'danger': '<i class="bi bi-x-octagon-fill me-2"></i>', 'warning': '<i class="bi bi-exclamation-triangle-fill me-2"></i>', 'info': '<i class="bi bi-info-circle-fill me-2"></i>'}[type] || '<i class="bi bi-info-circle-fill me-2"></i>';
        const toastHtml = `<div class="toast align-items-center ${bgColor} border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="3000"><div class="d-flex"><div class="toast-body d-flex align-items-center">${iconHtml}<span>${message}</span></div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div></div>`;
        const fragment = document.createRange().createContextualFragment(toastHtml);
        const toastEl = fragment.querySelector('.toast');
        toastContainer.appendChild(toastEl);
        const toast = new bootstrap.Toast(toastEl);
        toast.show();
        toastEl.addEventListener('hidden.bs.toast', () => { toastEl.remove(); });
    }

    const renameItemModal = document.getElementById('renameItemModal');
    if (renameItemModal) {
        renameItemModal.addEventListener('show.bs.modal', event => {
            const button = event.relatedTarget; const itemName = button.getAttribute('data-bs-item-name'); const isDir = button.getAttribute('data-bs-is-dir') === '1';
            const modalTitle = renameItemModal.querySelector('.modal-title'); const oldNameInput = renameItemModal.querySelector('#rename_old_name');
            const newNameInput = renameItemModal.querySelector('#rename_new_name'); const extensionSpan = renameItemModal.querySelector('#rename_extension');
            const inputGroupDiv = extensionSpan.parentElement;
            modalTitle.textContent = `'${itemName}' の名前を変更`; oldNameInput.value = itemName;
            if (isDir) {
                newNameInput.value = itemName; inputGroupDiv.classList.remove('input-group'); extensionSpan.style.display = 'none';
            } else {
                inputGroupDiv.classList.add('input-group'); const lastDotIndex = itemName.lastIndexOf('.');
                if (lastDotIndex > 0 && lastDotIndex < itemName.length - 1) {
                    newNameInput.value = itemName.substring(0, lastDotIndex); extensionSpan.textContent = itemName.substring(lastDotIndex); extensionSpan.style.display = 'inline-block'; inputGroupDiv.classList.add('input-group');
                } else {
                    newNameInput.value = itemName; extensionSpan.style.display = 'none'; inputGroupDiv.classList.remove('input-group');
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (phpMessage && phpMessage.type && phpMessage.text) { showToast(phpMessage.type, phpMessage.text); }
        
        function adjustLayout() {
            const header = document.querySelector('nav.navbar');
            const sidebar = document.getElementById('sidebarMenu');
            const mainContent = document.querySelector('.main-content');
            if (!header || !sidebar || !mainContent) return;
            
            const headerHeight = header.offsetHeight;
            sidebar.style.paddingTop = headerHeight + 'px';
            //mainContent.style.paddingTop = (headerHeight) + 'px';

            const sidebarSticky = sidebar.querySelector('.sidebar-sticky');
            if (sidebarSticky) {
                sidebarSticky.style.height = `calc(100vh - ${headerHeight}px)`;
            }
        }
        
        adjustLayout();
        window.addEventListener('resize', adjustLayout);

        const uploadFileForm = document.getElementById('uploadFileForm');
        if (uploadFileForm) {
            const uploadModalEl = document.getElementById('uploadFileModal');
            const uploadModal = new bootstrap.Modal(uploadModalEl);
            let isUploading = false;
            
            uploadModalEl.addEventListener('hide.bs.modal', function (event) {
                if (isUploading) {
                    event.preventDefault();
                }
            });

            uploadFileForm.addEventListener('submit', function(e) {
                e.preventDefault();
                isUploading = true;

                const submitBtn = document.getElementById('uploadSubmitBtn');
                const closeBtnHeader = document.getElementById('uploadModalCloseBtn');
                const closeBtnFooter = document.getElementById('uploadModalFooterCloseBtn');
                const originalBtnHtml = submitBtn.innerHTML;
                const filesInput = document.getElementById('files');

                if (filesInput.files.length === 0) {
                    showToast('warning', 'ファイルが選択されていません。');
                    isUploading = false;
                    return;
                }
                
                submitBtn.disabled = true;
                submitBtn.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> アップロード中...`;
                closeBtnHeader.disabled = true;
                closeBtnFooter.disabled = true;

                const formData = new FormData(this);

                fetch('index.php', {
                    method: 'POST',
                    body: formData,
                })
                .then(response => {
                    if (!response.ok) { throw new Error('サーバーエラーが発生しました。'); }
                    return response.json();
                })
                .then(data => {
                    if (data && data.type && data.text) {
                        if (data.type === 'success' || data.type === 'warning') {
                            isUploading = false;
                            uploadModal.hide();
                            showToast(data.type, data.text);
                            setTimeout(() => { location.reload(); }, 800);
                        } else {
                            showToast(data.type, data.text);
                        }
                    }
                })
                .catch(error => {
                    console.error('Upload Error:', error);
                    showToast('danger', 'アップロード処理中にエラーが発生しました。');
                })
                .finally(() => {
                    if (isUploading) {
                        isUploading = false;
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnHtml;
                        closeBtnHeader.disabled = false;
                        closeBtnFooter.disabled = false;
                        uploadFileForm.reset();
                    }
                });
            });
        }

        const selectAllCheckbox = document.getElementById('selectAllCheckbox');
        const itemCheckboxes = document.querySelectorAll('.item-checkbox');
        const breadcrumbContainer = document.getElementById('breadcrumbContainer');
        const tableActionsContainer = document.getElementById('tableActionsContainer');
        const selectionCountSpan = document.getElementById('selectionCount');
        const moveItemsJsonInput = document.getElementById('move_items_json');
        
        function updateActionHeader() {
            const selectedItems = Array.from(itemCheckboxes).filter(cb => cb.checked);
            const count = selectedItems.length;
            if (count > 0) {
                breadcrumbContainer.classList.add('d-none'); tableActionsContainer.classList.remove('d-none');
                selectionCountSpan.textContent = count;
                if(moveItemsJsonInput) { moveItemsJsonInput.value = JSON.stringify(selectedItems.map(cb => cb.value)); }
            } else {
                breadcrumbContainer.classList.remove('d-none'); tableActionsContainer.classList.add('d-none');
            }
        }

        if (selectAllCheckbox) { selectAllCheckbox.addEventListener('change', (e) => { itemCheckboxes.forEach(checkbox => { checkbox.checked = e.target.checked; }); updateActionHeader(); }); }
        itemCheckboxes.forEach(checkbox => { checkbox.addEventListener('change', () => { if (!checkbox.checked) { selectAllCheckbox.checked = false; } updateActionHeader(); }); });
        
        const sidebarNav = document.getElementById('sidebarMenu');
        if (sidebarNav) {
            sidebarNav.querySelectorAll('.toggle-icon').forEach(icon => {
                const targetCollapse = document.querySelector(icon.getAttribute('data-bs-target'));
                if (targetCollapse) {
                    targetCollapse.addEventListener('show.bs.collapse', () => { icon.textContent = '▾'; });
                    targetCollapse.addEventListener('hide.bs.collapse', () => { icon.textContent = '▸'; });
                }
            });
        }
    });
</script>
</body>
</html>