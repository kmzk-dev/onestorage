<?php
// 開発用
ini_set('display_errors', 1);
error_reporting(E_ALL);
// セッション
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
//初期設定
require_once __DIR__ . '/path.php';
require_once __DIR__ . '/functions/init_function.php';
require_once __DIR__ . '/functions/helper_function.php';
//認証
require_once __DIR__ . '/functions/cookie_function.php'; // 追加
require_once __DIR__ . '/functions/auth_function.php';
check_authentication();

require_once __DIR__ . '/functions/get_request_function.php';
require_once __DIR__ . '/functions/post_request_function.php';

// --- 画面 ---
$current_path_raw = $_GET['path'] ?? '';
// ★スターカテゴリの判定
$is_star_view = ($current_path_raw === 'starred');
$is_inbox_view = is_inbox_view($current_path_raw); // helper_function.phpで定義した関数を使用
$is_root_view = is_root_view($current_path_raw); // helper_function.phpで定義した関数を使用

if ($is_star_view) {
    $current_path = ''; // 疑似的なパス
    $web_path = ''; // 疑似的なウェブパス
    $items = [];
    $all_starred_items = load_star_config();

    // アイテムのメタデータを更新・検証しながらリストを作成
    foreach ($all_starred_items as $star_item) {
        // ★修正: INBOXアイテムの場合の物理パス解決ロジックを追加
        $is_inbox_starred = ($star_item['path'] === 'inbox');

        if ($is_inbox_starred) {
            // INBOXアイテムの場合は、物理パスに .inbox を使用
            // DATA_ROOTとINBOX_DIR_NAMEが定義されている前提
            $full_path = realpath(DATA_ROOT . DIRECTORY_SEPARATOR . INBOX_DIR_NAME . DIRECTORY_SEPARATOR . $star_item['name']);
        } else {
            // 通常のアイテムの場合は、保存されたパスを使用
            $item_path_from_root = ltrim($star_item['path'] . '/' . $star_item['name'], '/');
            $full_path = realpath(DATA_ROOT . '/' . $item_path_from_root);
        }
        // ★修正終わり

        if ($full_path !== false && strpos($full_path, DATA_ROOT) === 0) {
            // 存在チェックOK。サイズは再計算する方が安全
            $is_dir = is_dir($full_path);
            $size = $is_dir ? null : filesize($full_path);

            $items[] = [
                'name' => $star_item['name'],
                'path' => $star_item['path'],
                'is_dir' => $is_dir,
                'size' => $size,
                'formatted_size' => format_bytes($size),
                'is_starred' => true
            ];
        } else {
            // ファイルが存在しない場合は表示しない
        }
    }
    // ★INBOX表示の場合
} elseif ($is_inbox_view) {
    $current_path = get_inbox_path(); // 隠しディレクトリの絶対パスを取得
    $web_path = 'inbox'; // 疑似的なウェブパス
    $items = [];

    $all_items = array_diff(scandir($current_path), ['.', '..']);
    natsort($all_items);

    $starred_items = load_star_config();
    $starred_hashes = [];
    foreach ($starred_items as $star) {
        // INBOXの論理パス 'inbox' にあるアイテムのハッシュのみをチェック
        if ($star['path'] === $web_path) {
            $starred_hashes[get_item_hash($star['path'], $star['name'])] = true;
        }
    }

    foreach ($all_items as $item) {
        if (str_starts_with($item, '.')) continue; // .で始まるファイルは無視
        $item_path = $current_path . '/' . $item;
        $is_dir = is_dir($item_path);

        // INBOX内ではフォルダ作成が禁止されているため、フォルダは表示しない
        if ($is_dir) continue;

        $size = filesize($item_path);
        $item_hash = get_item_hash($web_path, $item);

        $items[] = [
            'name' => $item,
            'is_dir' => false,
            'size' => $size,
            'formatted_size' => format_bytes($size),
            'is_starred' => isset($starred_hashes[$item_hash]),
            'path' => 'inbox', // INBOXアイテムのパスは 'inbox' を使用
        ];
    }
} else {
    // 通常のフォルダ表示の場合
    $current_path = realpath(DATA_ROOT . '/' . $current_path_raw);
    if ($current_path === false || strpos($current_path, DATA_ROOT) !== 0) $current_path = DATA_ROOT;
    $web_path = ltrim(substr($current_path, strlen(DATA_ROOT)), '/');
    $web_path = str_replace('\\', '/', $web_path);
    $items = [];
    $all_items = array_diff(scandir($current_path), ['.', '..']);
    natsort($all_items);

    $starred_items = load_star_config();
    $starred_hashes = [];
    foreach ($starred_items as $star) {
        // パフォーマンスのため、現在のディレクトリにあるアイテムのハッシュのみをチェック
        if ($star['path'] === $web_path) {
            $starred_hashes[get_item_hash($star['path'], $star['name'])] = true;
        }
    }

    foreach ($all_items as $item) {
        if (str_starts_with($item, '.')) continue;
        $item_path = $current_path . '/' . $item;
        $is_dir = is_dir($item_path);
        $size = $is_dir ? null : filesize($item_path);

        $item_hash = get_item_hash($web_path, $item);

        $items[] = [
            'name' => $item,
            'is_dir' => $is_dir,
            'size' => $size,
            'formatted_size' => format_bytes($size),
            'is_starred' => isset($starred_hashes[$item_hash]), // ★スター状態を設定
            'path' => $web_path,
        ];
    }
}

// フォルダ表示の場合のみソート（スター表示の場合は既にロード時にソート済み）
if (!$is_star_view) {
    usort($items, fn($a, $b) => ($a['is_dir'] !== $b['is_dir']) ? ($a['is_dir'] ? -1 : 1) : strcasecmp($a['name'], $b['name']));
}

$dir_cache = load_dir_cache();
$sidebar_folders = $dir_cache['tree'];
$all_dirs = $dir_cache['list'];
$breadcrumbs = [];

if ($is_star_view) {
    // スター表示のパンくずリスト
    $breadcrumbs[] = ['name' => 'Starred Items', 'path' => 'starred'];
} elseif ($is_inbox_view) {
    // INBOX表示のパンくずリスト
    $breadcrumbs[] = ['name' => 'INBOX', 'path' => 'inbox'];
} else {
    // ★修正: ルートとサブフォルダ表示の場合、最初に 'home' を追加
    $breadcrumbs[] = ['name' => 'home', 'path' => ''];

    if (!empty($web_path)) {
        // サブフォルダパスを構成要素ごとに分割して追加
        $tmp_path = '';
        foreach (explode('/', $web_path) as $part) {
            $tmp_path .= (empty($tmp_path) ? '' : '/') . $part;
            $breadcrumbs[] = ['name' => $part, 'path' => $tmp_path];
        }
    }
}
$message = $_SESSION['message'] ?? null;
unset($_SESSION['message']);
$json_message = json_encode($message);

// ★スターカテゴリ専用の変数
$STAR_API_URL = 'functions/star_api_function.php';
$json_star_view = json_encode($is_star_view);
?>
<!DOCTYPE html>
<html lang="ja">
<?php require_once __DIR__ . '/templates/head.php'; ?>

<body>
    <?php require_once __DIR__ . '/templates/nav.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <nav id="sidebarMenu" class="col-md-4 col-lg-3 d-md-block bg-ligth sidebar collapse d-md-flex flex-column">

                <div id="sidebarTopFixed" class="py-3 flex-shrink-0">
                    <ul class="nav flex-column px-3">
                        <li class="nav-item">
                            <a class="nav-link <?= $is_star_view ? 'active' : '' ?>" href="?path=starred">
                                <i class="bi bi-star-fill me-2" style="color: gold;"></i>Starred Items
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $is_inbox_view ? 'active' : '' ?>" href="?path=inbox">
                                <i class="bi bi-inbox-fill me-2 text-info"></i>INBOX
                            </a>
                        </li>
                        <hr>
                        <li class="nav-item">
                            <a class="nav-link px-3 <?= (empty($web_path) && !$is_star_view) ? 'active' : '' ?>" href="?path=">
                                <i class="bi bi-house-door me-2"></i>home
                            </a>
                        </li>
                    </ul>
                </div>

                <div id="sidebarScrollable" class="sidebar-sticky overflow-auto">
                    <ul class="nav flex-column mb-2">
                        <?php
                        function render_folder_tree($folders, $current_path, $level = 0 ,$max_depth = 2)
                        {
                            $html = '';
                            $indent_px = 16 * ($level + 1);
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
                                // ★修正: max_depthを超えていない場合のみ再帰的に子要素をレンダリング
                                if ($has_children && $level < $max_depth) {
                                    $html .= '<div class="collapse ' . ($is_collapsed_open ? 'show' : '') . '" id="' . $collapse_id . '"><ul class="nav flex-column">' . render_folder_tree($folder['children'], $current_path, $level + 1, $max_depth) . '</ul></div>'; // ★ max_depthを渡す
                                }
                                $html .= '</li>';
                            }
                            return $html;
                        }
                        echo render_folder_tree($sidebar_folders, $web_path);
                        ?>
                    </ul>
                </div>

                <div id="sidebarBottomFixed" class="p-3 border-top flex-shrink-0 text-center">
                    <img class="img-fluid" src="img/Gemini_Generated_Image_dpkroidpkroidpkr.png" alt="ONE STORAGE Logo" style="width: 100%; max-width: 150px; margin: 0 auto;">
                </div>
            </nav>

            <main class="col-md-8 ms-sm-auto col-lg-9 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <div id="breadcrumbContainer" class="flex-grow-1">
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <?php foreach ($breadcrumbs as $crumb): ?>
                                    <li class="breadcrumb-item">
                                        <?php if ($crumb['name'] === 'Starred Items'): ?>
                                            <a href="?path=starred" class="text-dark text-decoration-none">
                                                <i class="bi bi-star-fill me-1 text-warning"></i><?= htmlspecialchars($crumb['name'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                        <?php elseif ($crumb['name'] === 'INBOX'): ?>
                                            <a href="?path=inbox" class="text-dark text-decoration-none">
                                                <i class="bi bi-inbox-fill me-1 text-info"></i><?= htmlspecialchars($crumb['name'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                        <?php elseif ($crumb['name'] === 'home'): ?>
                                            <a href="?path=inbox" class="text-dark text-decoration-none">
                                                <i class="bi bi-inbox-fill me-1 text-info"></i>home
                                            </a>
                                        <?php else: ?>
                                            <a href="?path=<?= urlencode($crumb['path']) ?>" class="text-dark text-decoration-none">
                                                <?= htmlspecialchars($crumb['name'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        </nav>
                    </div>
                    <div id="tableActionsContainer" class="d-none">
                        <span class="text-muted me-3"><strong id="selectionCount">0</strong>個選択中</span>
                        <?php if (!$is_star_view): ?>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#moveItemsModal"><i class="bi bi-folder-symlink"></i> 選択項目を移動</button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="file-list">
                    <div class="row gx-2 text-muted border-bottom py-2 d-none d-md-flex small">
                        <div class="col-auto" style="width: 30px;"></div>
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
                        <?php foreach ($items as $item):
                            // ★スタービューでは元のパスを使用し、通常ビューでは現在のパスを使用
                            $item_web_path_for_action = $item['path'] ?? $web_path;
                            $item_full_web_path = ltrim($item_web_path_for_action . '/' . $item['name'], '/');
                            $is_starred = $item['is_starred'] ?? false;
                        ?>
                            <div class="row gx-2 d-flex align-items-center border-bottom file-row">
                                <div class="col-auto py-2">
                                    <button type="button" class="btn btn-sm btn-light star-toggle-btn"
                                        data-web-path="<?= htmlspecialchars($item_web_path_for_action, ENT_QUOTES, 'UTF-8') ?>"
                                        data-item-name="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-is-dir="<?= $item['is_dir'] ? '1' : '0' ?>"
                                        title="<?= $is_starred ? 'スターを解除' : 'スターに登録' ?>">
                                        <i class="bi bi-star<?= $is_starred ? '-fill text-warning' : ' text-muted' ?>"></i>
                                    </button>
                                </div>
                                <div class="col-auto py-2">
                                    <input class="form-check-input item-checkbox" type="checkbox" value="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                                <div class="col text-truncate py-2">
                                    <?php if ($item['is_dir']): ?>
                                        <a href="?path=<?= urlencode($is_star_view ? $item_web_path_for_action : ltrim($web_path . '/' . $item['name'], '/')) ?>" class="d-flex align-items-center">
                                            <i class="bi bi-folder-fill text-primary me-2 fs-5"></i>
                                            <span><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php if ($is_star_view): ?>
                                                <span class="ms-2 badge bg-secondary-subtle text-secondary fw-normal small">in: /<?= htmlspecialchars($item_web_path_for_action, ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php endif; ?>
                                        </a>
                                    <?php else: ?>
                                        <a href="?action=view&path=<?= urlencode($item_full_web_path) ?>" target="_blank" class="d-flex align-items-center">
                                            <i class="bi bi-file-earmark-text me-2 fs-5"></i>
                                            <span><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php if ($is_star_view): ?>
                                                <span class="ms-2 badge bg-secondary-subtle text-secondary fw-normal small">in: /<?= htmlspecialchars($item_web_path_for_action, ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php endif; ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div class="col-3 d-none d-md-block col-lg-2 text-end py-2">
                                    <?= htmlspecialchars($item['formatted_size'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <div class="col-auto py-2">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="アクション">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">

                                            <?php if (!$is_star_view): ?>
                                                <li><button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#renameItemModal" data-bs-item-name="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>" data-bs-is-dir="<?= $item['is_dir'] ? '1' : '0' ?>"><i class="bi bi-pencil-fill me-2"></i>名前の変更</button></li>
                                            <?php endif; ?>

                                            <li><a class="dropdown-item" href="?action=download&path=<?= urlencode($item_full_web_path) ?>"><i class="bi bi-download me-2"></i>ダウンロード</a></li>

                                            <?php if ($is_star_view): ?>
                                                <li>
                                                    <hr class="dropdown-divider">
                                                </li>
                                                <li><a class="dropdown-item" href="?path=<?= urlencode($item_web_path_for_action) ?>"><i class="bi bi-arrow-return-right me-2"></i>元のフォルダへ</a></li>
                                            <?php endif; ?>

                                            <?php if (!$is_star_view): ?>
                                                <li>
                                                    <hr class="dropdown-divider">
                                                </li>
                                                <li>
                                                    <form action="index.php" method="post" onsubmit="return confirm('本当に「<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>」を削除しますか？\nこの操作は元に戻せません。');">
                                                        <input type="hidden" name="action" value="delete_item">
                                                        <input type="hidden" name="path" value="<?= htmlspecialchars($item_web_path_for_action, ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="item_name" value="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash-fill me-2"></i>削除</button>
                                                    </form>
                                                </li>
                                            <?php endif; ?>

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

    <?php
    // トースト
    require_once __DIR__ . '/templates/components/toast_container.php';
    // モーダル
    require_once __DIR__ . '/templates/components/create_folder_modal.php';
    require_once __DIR__ . '/templates/components/upload_file_modal.php';
    require_once __DIR__ . '/templates/components/rename_item_modal.php';
    require_once __DIR__ . '/templates/components/move_item_modal.php'
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const phpMessage = <?= $json_message ?>;
        // ★スター機能の追加
        const STAR_API_URL = '<?= $STAR_API_URL ?>';
        const isStarView = <?= $json_star_view ?>;

        function showToast(type, message) {
            const toastContainer = document.querySelector('.toast-container');
            if (!toastContainer) return;
            const bgColor = {
                'success': 'text-bg-success',
                'danger': 'text-bg-danger',
                'warning': 'text-bg-warning',
                'info': 'text-bg-primary'
            } [type] || 'text-bg-primary';
            const iconHtml = {
                'success': '<i class="bi bi-check-circle-fill me-2"></i>',
                'danger': '<i class="bi bi-x-octagon-fill me-2"></i>',
                'warning': '<i class="bi bi-exclamation-triangle-fill me-2"></i>',
                'info': '<i class="bi bi-info-circle-fill me-2"></i>'
            } [type] || '<i class="bi bi-info-circle-fill me-2"></i>';
            const toastHtml = `<div class="toast align-items-center ${bgColor} border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="3000"><div class="d-flex"><div class="toast-body d-flex align-items-center">${iconHtml}<span>${message}</span></div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div></div>`;
            const fragment = document.createRange().createContextualFragment(toastHtml);
            const toastEl = fragment.querySelector('.toast');
            toastContainer.appendChild(toastEl);
            const toast = new bootstrap.Toast(toastEl);
            toast.show();
            toastEl.addEventListener('hidden.bs.toast', () => {
                toastEl.remove();
            });
        }

        const renameItemModal = document.getElementById('renameItemModal');
        if (renameItemModal) {
            renameItemModal.addEventListener('show.bs.modal', event => {
                const button = event.relatedTarget;
                const itemName = button.getAttribute('data-bs-item-name');
                const isDir = button.getAttribute('data-bs-is-dir') === '1';
                const modalTitle = renameItemModal.querySelector('.modal-title');
                const oldNameInput = renameItemModal.querySelector('#rename_old_name');
                const newNameInput = renameItemModal.querySelector('#rename_new_name');
                const extensionSpan = renameItemModal.querySelector('#rename_extension');
                const inputGroupDiv = extensionSpan.parentElement;
                modalTitle.textContent = `'${itemName}' の名前を変更`;
                oldNameInput.value = itemName;
                if (isDir) {
                    newNameInput.value = itemName;
                    inputGroupDiv.classList.remove('input-group');
                    extensionSpan.style.display = 'none';
                } else {
                    inputGroupDiv.classList.add('input-group');
                    const lastDotIndex = itemName.lastIndexOf('.');
                    if (lastDotIndex > 0 && lastDotIndex < itemName.length - 1) {
                        newNameInput.value = itemName.substring(0, lastDotIndex);
                        extensionSpan.textContent = itemName.substring(lastDotIndex);
                        extensionSpan.style.display = 'inline-block';
                        inputGroupDiv.classList.add('input-group');
                    } else {
                        newNameInput.value = itemName;
                        extensionSpan.style.display = 'none';
                        inputGroupDiv.classList.remove('input-group');
                    }
                }
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            if (phpMessage && phpMessage.type && phpMessage.text) {
                showToast(phpMessage.type, phpMessage.text);
            }

            // ★修正: モバイル対応のための絶対高さ計算ロジック
            function adjustLayout() {
                const header = document.querySelector('nav.navbar');
                const sidebar = document.getElementById('sidebarMenu');
                if (!header || !sidebar) return;

                const headerHeight = header.offsetHeight;

                // 1. sidebarのtop位置をナビゲーションバーの高さに設定 (これは必須)
                sidebar.style.top = headerHeight + 'px';

                // モバイル表示 (mdブレークポイント未満) では計算を解除する
                const isMobile = window.innerWidth < 768;
                const sidebarSticky = document.getElementById('sidebarScrollable');

                if (isMobile) {
                    // モバイルでは絶対高さ計算を解除し、コンテンツ量に応じて自然な高さを取る
                    if (sidebarSticky) {
                        sidebarSticky.style.height = ''; // heightスタイルをクリア
                    }
                    return;
                }

                // --- デスクトップ表示での絶対高さ計算 ---

                // 2. 上部固定部と下部固定部の高さを取得
                const topFixed = document.getElementById('sidebarTopFixed');
                const bottomFixed = document.getElementById('sidebarBottomFixed');

                // getBoundingClientRect().height でPadding, Borderも含めた正確な高さを取得
                const topFixedHeight = topFixed ? topFixed.getBoundingClientRect().height : 0;
                const bottomFixedHeight = bottomFixed ? bottomFixed.getBoundingClientRect().height : 0;

                // 3. サイドバーの親コンテナ（#sidebarMenu）が占めている実際の高さを取得
                const sidebarActualHeight = sidebar.getBoundingClientRect().height;

                // 4. 計算: (サイドバー全体の高さ) - (上部固定部の高さ) - (下部固定部の高さ)
                const requiredHeight = sidebarActualHeight - topFixedHeight - bottomFixedHeight;

                if (sidebarSticky) {
                    // heightスタイルをpx値で直接設定することで、可変エリアに絶対的な領域を与えます。
                    // これにより、コンテンツが少ない場合でも 'hoge' が最下部に固定されます。
                    sidebarSticky.style.height = requiredHeight + 'px';
                }
            }

            adjustLayout();
            window.addEventListener('resize', adjustLayout);

            const uploadFileForm = document.getElementById('uploadFileForm');
            if (uploadFileForm) {
                const uploadModalEl = document.getElementById('uploadFileModal');
                const uploadModal = new bootstrap.Modal(uploadModalEl);
                const submitBtn = document.getElementById('uploadSubmitBtn');
                const filesInput = document.getElementById('files');
                const progressContainer = document.getElementById('uploadProgressContainer');
                const progressBar = document.getElementById('uploadProgressBar');
                const uploadFileName = document.getElementById('uploadFileName');
                const uploadStatusText = document.getElementById('uploadStatusText');
                const closeBtnFooter = document.getElementById('uploadModalFooterCloseBtn');

                let isUploading = false;
                let uploadQueue = [];

                uploadModalEl.addEventListener('hide.bs.modal', function(event) {
                    if (isUploading) {
                        event.preventDefault();
                        showToast('warning', 'アップロード処理が完了するまでモーダルを閉じることはできません。');
                    }
                });

                uploadFileForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    if (isUploading) return;

                    if (filesInput.files.length === 0) {
                        showToast('warning', 'ファイルが選択されていません。');
                        return;
                    }

                    uploadQueue = Array.from(filesInput.files);
                    processUploadQueue();
                });

                async function processUploadQueue() {
                    if (uploadQueue.length === 0) {
                        isUploading = false;
                        showToast('success', 'すべてのファイルのアップロードが完了しました。');
                        setTimeout(() => {
                            location.reload();
                        }, 800);
                        return;
                    }

                    isUploading = true;
                    setUploadUiState(true);

                    const file = uploadQueue.shift(); // キューの先頭からファイルを取得

                    // .で始まるファイルのアップロードを禁止
                    if (file.name.startsWith('.')) {
                        showToast('warning', `[${file.name}] はドットで始まるためスキップされました。`);
                        processUploadQueue(); // 次のファイルへ
                        return;
                    }

                    await uploadFileInChunks(file);

                    processUploadQueue(); // 次のファイルの処理へ
                }

                async function uploadFileInChunks(file) {
                    const CHUNK_SIZE = 5 * 1024 * 1024; // 5MB per chunk
                    const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
                    let chunkIndex = 0;

                    updateProgress(0, file.name, `(1/${totalChunks})`);

                    for (chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
                        const start = chunkIndex * CHUNK_SIZE;
                        const end = Math.min(start + CHUNK_SIZE, file.size);
                        const chunk = file.slice(start, end);

                        const formData = new FormData();
                        formData.append('action', 'upload_chunk');
                        formData.append('path', document.getElementById('upload_path').value);
                        formData.append('chunk', chunk, file.name);
                        formData.append('original_name', file.name);
                        formData.append('chunk_index', chunkIndex);
                        formData.append('total_chunks', totalChunks);
                        formData.append('total_size', file.size);

                        try {
                            const response = await fetch('index.php', {
                                method: 'POST',
                                body: formData
                            });

                            if (!response.ok) {
                                throw new Error('サーバーエラーが発生しました。');
                            }

                            const data = await response.json();

                            if (data.type === 'danger' || data.type === 'warning') {
                                throw new Error(data.text);
                            }

                            if (data.type === 'success') {
                                updateProgress(100, file.name, '完了');
                            } else {
                                const progress = Math.round(((chunkIndex + 1) / totalChunks) * 100);
                                updateProgress(progress, file.name, `(${chunkIndex + 2 > totalChunks ? totalChunks : chunkIndex + 2}/${totalChunks})`);
                            }

                        } catch (error) {
                            showToast('danger', `[${file.name}] のアップロードに失敗しました: ${error.message}`);
                            setUploadUiState(false);
                            uploadQueue = []; // エラーが発生したらキューをクリア
                            return;
                        }
                    }
                }

                function setUploadUiState(uploading) {
                    isUploading = uploading;
                    submitBtn.disabled = uploading;
                    closeBtnFooter.disabled = uploading;
                    filesInput.disabled = uploading;

                    if (uploading) {
                        progressContainer.classList.remove('d-none');
                        submitBtn.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> アップロード中...`;
                    } else {
                        progressContainer.classList.add('d-none');
                        submitBtn.innerHTML = 'アップロード';
                        uploadFileForm.reset();
                    }
                }

                function updateProgress(percentage, name, status) {
                    uploadFileName.textContent = name;
                    progressBar.style.width = percentage + '%';
                    progressBar.setAttribute('aria-valuenow', percentage);
                    progressBar.textContent = percentage + '%';
                }
            }

            // ★スター機能のトグル処理
            const starToggleBtns = document.querySelectorAll('.star-toggle-btn');
            starToggleBtns.forEach(button => {
                button.addEventListener('click', async (e) => {
                    e.preventDefault();
                    const webPath = button.getAttribute('data-web-path');
                    const itemName = button.getAttribute('data-item-name');
                    const isDir = button.getAttribute('data-is-dir') === '1';
                    const icon = button.querySelector('i');

                    // ロード状態を一時的に設定
                    const originalIconClass = icon.className;
                    const originalTitle = button.title;
                    icon.className = 'bi bi-arrow-repeat spin-animation text-info';
                    button.disabled = true;

                    try {
                        const response = await fetch(STAR_API_URL, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                action: 'toggle_star',
                                data: {
                                    web_path: webPath,
                                    item_name: itemName,
                                    is_dir: isDir
                                }
                            })
                        });

                        const data = await response.json();

                        if (data.success) {
                            showToast('success', data.message);

                            // UIを更新
                            if (data.action === 'added') {
                                icon.className = 'bi bi-star-fill text-warning';
                                button.title = 'スターを解除';
                            } else if (data.action === 'removed') {
                                icon.className = 'bi bi-star text-muted';
                                button.title = 'スターに登録';

                                // スタービューの場合はアイテムをリストから削除し、ページをリロードしてリストを更新
                                if (isStarView) {
                                    // 最も近い file-row を削除
                                    const row = button.closest('.file-row');
                                    if (row) {
                                        row.remove();
                                    }
                                    // すべて削除されたら再ロード
                                    if (document.querySelectorAll('.file-row').length === 0) {
                                        location.reload();
                                    }
                                }
                            }
                        } else {
                            showToast('danger', data.message);
                            icon.className = originalIconClass; // エラー時は元に戻す
                            button.title = originalTitle;
                        }
                    } catch (error) {
                        showToast('danger', `スター操作中にエラーが発生しました: ${error.message}`);
                        icon.className = originalIconClass; // エラー時は元に戻す
                        button.title = originalTitle;
                    } finally {
                        button.disabled = false;
                        // スピンアニメーション用のクラスを削除するために再設定
                        if (icon.className.includes('spin-animation')) {
                            icon.className = icon.className.replace(' spin-animation', '');
                        }
                    }
                });
            });

            // スピンアニメーションのCSSをインラインで追加
            const style = document.createElement('style');
            style.textContent = `
            .spin-animation {
                animation: spin 1s linear infinite;
            }
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
        `;
            document.head.appendChild(style);


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
                    breadcrumbContainer.classList.add('d-none');
                    tableActionsContainer.classList.remove('d-none');
                    selectionCountSpan.textContent = count;
                    if (moveItemsJsonInput) {
                        moveItemsJsonInput.value = JSON.stringify(selectedItems.map(cb => cb.value));
                    }
                } else {
                    breadcrumbContainer.classList.remove('d-none');
                    tableActionsContainer.classList.add('d-none');
                }
            }

            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', (e) => {
                    itemCheckboxes.forEach(checkbox => {
                        checkbox.checked = e.target.checked;
                    });
                    updateActionHeader();
                });
            }
            itemCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', () => {
                    if (!checkbox.checked) {
                        selectAllCheckbox.checked = false;
                    }
                    updateActionHeader();
                });
            });

            const sidebarNav = document.getElementById('sidebarMenu');
            if (sidebarNav) {
                sidebarNav.querySelectorAll('.toggle-icon').forEach(icon => {
                    const targetCollapse = document.querySelector(icon.getAttribute('data-bs-target'));
                    if (targetCollapse) {
                        targetCollapse.addEventListener('show.bs.collapse', () => {
                            icon.textContent = '▾';
                        });
                        targetCollapse.addEventListener('hide.bs.collapse', () => {
                            icon.textContent = '▸';
                        });
                    }
                });
            }
        });
    </script>
</body>

</html>