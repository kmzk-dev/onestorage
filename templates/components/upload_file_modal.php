<div class="modal fade" id="uploadFileModal" tabindex="-1" data-bs-backdrop="true" data-bs-keyboard="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">ファイルのアップロード</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" id="uploadModalCloseBtn"></button>
            </div>
            <form id="uploadFileForm" action="index.php" method="post" enctype="multipart/form-data">
                <div class="modal-body">
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
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="uploadModalFooterCloseBtn">閉じる</button>
                    <button type="submit" id="uploadSubmitBtn" class="btn btn-primary">アップロード</button>
                </div>
            </form>
        </div>
    </div>
</div>