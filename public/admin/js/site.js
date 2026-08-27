
//  htmx 拓展 bny-url
//  当响应为 JSON 且包含 url 属性（非空）时，延时跳转到 url 指定页面。
//  延时由 time 属性决定（秒），默认 3 秒。
(function () {
    htmx.defineExtension('bny-url', {
        onEvent: function (name, evt) {
            if (name !== 'htmx:afterRequest') return;

            var xhr = evt.detail.xhr;
            if (!xhr) return;

            var ct = (xhr.getResponseHeader('content-type') || '').toLowerCase();
            if (ct.indexOf('application/json') === -1) return;

            var data;
            try { data = JSON.parse(xhr.responseText); } catch (e) { return; }

            if (!data.url) return;

            var seconds = parseFloat(data.time);
            if (!seconds || seconds <= 0) seconds = 3;

            setTimeout(function () {
                window.location.href = data.url;
            }, seconds * 1000);
        }
    });
})();

// 全屏切换：进/出全屏 + 按钮图标与标题同步
(function () {
    function isFull() {
        return !!(
            document.fullscreenElement ||
            document.webkitFullscreenElement
        );
    }

    function syncFullBtn() {
        var full = isFull();
        var btns = document.querySelectorAll('.admin-fullscreen-btn');
        for (var i = 0; i < btns.length; i++) {
            var inIcon = btns[i].querySelector('.icon-fullscreen');
            var outIcon = btns[i].querySelector('.icon-fullscreen-exit');
            if (inIcon) inIcon.style.display = full ? 'none' : '';
            if (outIcon) outIcon.style.display = full ? '' : 'none';
            btns[i].tip = full ? '退出全屏' : '全屏';
        }
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.admin-fullscreen-btn');
        if (!btn) return;
        var el = document.documentElement;
        var exit = document.exitFullscreen || document.webkitExitFullscreen;
        var enter = el.requestFullscreen || el.webkitRequestFullscreen;
        if (isFull()) {
            if (exit) exit.call(document);
        } else if (enter) {
            var r = enter.call(el);
            if (r && r.catch) r.catch(function () { });
        }
    });

    document.addEventListener('fullscreenchange', syncFullBtn);
    document.addEventListener('webkitfullscreenchange', syncFullBtn);
})();

// 附件选择器：勾选/单选限数/搜索过滤/全选/确认回填
(function () {
    var KEY = 'data-attach-inited';

    function initAttach(box) {
        if (!box || box.getAttribute(KEY) === '1') return;
        box.setAttribute(KEY, '1');

        var grid = box.querySelector('.attach-grid');
        if (!grid) return;
        var search = box.querySelector('.attach-search input');
        var all = box.querySelector('.attach-all input');
        var countEl = box.querySelector('.attach-count b');
        var confirmBtn = box.querySelector('#attach-confirm');
        var empty = box.querySelector('.attach-empty');
        var items = Array.prototype.slice.call(grid.querySelectorAll('.attach-item'));

        var max = parseInt(box.getAttribute('data-max') || '1', 10);
        var target = box.getAttribute('data-target') || '';

        function visible() {
            return items.filter(function (it) { return it.style.display !== 'none'; });
        }

        function allChecked() {
            var vis = visible();
            var n = 0;
            for (var i = 0; i < vis.length; i++) {
                if (vis[i].querySelector('.bny-checkbox').checked) n++;
            }
            if (countEl) countEl.textContent = n;
            if (all) all.checked = n > 0 && n === vis.length;
        }

        allChecked();

        // 勾选：高亮 + 计数，单选/上限控制
        grid.addEventListener('change', function (e) {
            var input = e.target;
            if (input.type !== 'checkbox' || !input.closest('.attach-item')) return;
            if (input.checked && max > 0) {
                var checked = grid.querySelectorAll('.bny-checkbox:checked').length;
                if (max === 1 && checked > 1) {
                    // 单选：取消其它选择
                    for (var i = 0; i < items.length; i++) {
                        var c = items[i].querySelector('.bny-checkbox');
                        if (c !== input && c.checked) c.checked = false;
                    }
                } else if (checked > max) {
                    input.checked = false;
                    if (window.bny && bny.alert) bny.alert('最多只能选择 ' + max + ' 个附件', 3);
                    return;
                }
            }
            for (var j = 0; j < items.length; j++) {
                items[j].classList.toggle('checked', items[j].querySelector('.bny-checkbox').checked);
            }
            allChecked();
        });

        // 搜索：按文件名过滤
        if (search) {
            search.addEventListener('input', function () {
                var kw = search.value.trim().toLowerCase();
                var n = 0;
                for (var s = 0; s < items.length; s++) {
                    var name = (items[s].getAttribute('data-name') || '').toLowerCase();
                    var hit = !kw || name.indexOf(kw) !== -1;
                    items[s].style.display = hit ? '' : 'none';
                    if (hit) n++;
                }
                if (empty) empty.style.display = n ? 'none' : 'flex';
                allChecked();
            });
        }

        // 全选（仅当前可见项，受上限约束）
        if (all) {
            all.addEventListener('change', function () {
                var vis = visible();
                var checked = all.checked;
                for (var a = 0; a < vis.length; a++) {
                    var c = vis[a].querySelector('.bny-checkbox');
                    // 有限制时，全选只勾到上限为止
                    var canCheck = checked && (max === 0 || a < max);
                    if (c.checked !== canCheck) c.checked = canCheck;
                }
                for (var b = 0; b < items.length; b++) {
                    items[b].classList.toggle('checked', items[b].querySelector('.bny-checkbox').checked);
                }
                allChecked();
            });
        }

        // 确认：回填目标字段并关闭弹层
        if (confirmBtn) {
            confirmBtn.addEventListener('click', function () {
                var picked = [];
                for (var p = 0; p < items.length; p++) {
                    var pc = items[p].querySelector('.bny-checkbox');
                    if (pc.checked) picked.push(pc.value);
                }
                if (!picked.length) {
                    if (window.bny && bny.alert) bny.alert('请先选择附件', 3);
                    return;
                }
                if (target) {
                    var field = document.getElementById(target);
                    if (field) {
                        if (max === 1) {
                            // 单选：替换
                            field.value = picked[0];
                        } else {
                            // 多选：与已有值合并去重（已有在前）
                            var arr = ((field.value || '').trim() ? field.value.trim().split(',') : []);
                            picked.forEach(function (pv) {
                                if (arr.indexOf(pv) === -1) arr.push(pv);
                            });
                            field.value = arr.join(',');
                        }
                        // 通知父页面表单感知变化
                        try {
                            field.dispatchEvent(new Event('input', { bubbles: true }));
                        } catch (e) { }
                    }
                }
                var page = box.closest('.bny-page');
                if (page) {
                    var closeBtn = page.querySelector('.close-btn');
                    if (closeBtn) closeBtn.click();
                }
            });
        }
    }

    function tryInit(root) {
        if (!root) return;
        if (root.querySelector) {
            var boxes = root.classList && root.classList.contains('attach-select')
                ? [root]
                : Array.prototype.slice.call(root.querySelectorAll('.attach-select'));
            for (var i = 0; i < boxes.length; i++) initAttach(boxes[i]);
        }
    }

    // 兜底：bny-page 等以 innerHTML 方式注入的内容不会派发 htmx:load，
    // 用 MutationObserver 监听 DOM 变化，出现 .attach-select 即初始化
    function watchBody() {
        if (!window.MutationObserver || !document.body) return;
        new MutationObserver(function (mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var nodes = mutations[i].addedNodes;
                for (var j = 0; j < nodes.length; j++) {
                    var n = nodes[j];
                    if (!n || n.nodeType !== 1) continue;
                    tryInit(n);
                }
            }
        }).observe(document.body, { childList: true, subtree: true });
    }

    function boot() {
        tryInit(document);
        watchBody();
    }

    document.addEventListener('htmx:load', function (e) { tryInit(e.target); });
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();

// 图片 input 组件（bny-form 集合）：
// bny-input-group 内放置 .bny-image-group 时，联动同组文本框做实时预览。
// 逗号分隔的地址逐张预览（多图自动层叠）；空输入不预览，非图片地址自动跳过。
// 用法见 system/base.html 的"站点logo"字段。
(function () {
    var IMG_KEY = 'data-img-input-inited';

    // 触发一次 htmx:load，让 bunny 的 bny-image-group 完成包裹与点击放大绑定
    function fireLoad(node) {
        try {
            node.dispatchEvent(new CustomEvent('htmx:load', {
                bubbles: true,
                detail: { elt: node }
            }));
        } catch (e) { }
    }

    function bind(box) {
        if (!box || box.getAttribute(IMG_KEY) === '1') return;
        box.setAttribute(IMG_KEY, '1');

        var group = box.querySelector('.bny-image-group');
        var input = box.querySelector(':scope > input.bny-input, input.bny-input');
        if (!group || !input) return;

        // 是否为图片地址（按扩展名判断，含 query 参数）
        function isImageUrl(src) {
            var path;
            try { path = new URL(src, window.location.href).pathname; } catch (e) { path = src; }
            return /\.(png|jpe?g|gif|webp|svg|bmp|ico|avif|apng|tiff?)$/i.test(path);
        }

        function render() {
            var raw = (input.value || '').trim();
            // 逗号分隔 → 逐段去空格、去空段；仅保留图片地址，非图片一律不预览
            var urls = raw
                ? raw.split(',').map(function (s) { return s.trim(); }).filter(Boolean).filter(isImageUrl)
                : [];

            var imgs = group.querySelectorAll('img[img-preview]');
            // 复用旧图，仅补充不足的
            for (var i = 0; i < urls.length; i++) {
                var cx = imgs[i];
                if (!cx) {
                    cx = document.createElement('img');
                    cx.setAttribute('img-preview', '');
                    cx.setAttribute('alt', '');
                    group.appendChild(cx);
                }
                if (cx.getAttribute('data-raw-src') !== urls[i]) {
                    cx.setAttribute('data-raw-src', urls[i]);
                    cx.src = urls[i];
                }
            }
            // 移除多余的旧图（含输入为空时的全部预览）
            for (var j = urls.length; j < imgs.length; j++) {
                var extra = imgs[j];
                if (extra.parentNode) extra.parentNode.remove();
            }
            fireLoad(group);
        }

        input.addEventListener('input', render);
        render();
    }

    function tryBind(root) {
        if (!root || !root.querySelector) return;
        var list = root.classList && root.classList.contains('bny-input-group')
            ? [root]
            : Array.prototype.slice.call(root.querySelectorAll('.bny-input-group'));
        for (var i = 0; i < list.length; i++) {
            if (list[i].querySelector('.bny-image-group')) bind(list[i]);
        }
    }

    document.addEventListener('htmx:load', function (e) { tryBind(e.target); });
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { tryBind(document); });
    } else {
        tryBind(document);
    }
})();

// 上传弹层：点击/拖拽选择 → 图片可压缩（1~10 强度）→ 上传接口
(function () {
    var UP_KEY = 'data-upload-inited';

    function fileIcon(name) {
        var ext = (name.split('.').pop() || '').toLowerCase();
        if (/^(png|jpe?g|gif|webp|bmp|svg|ico|avif)$/.test(ext)) return 'icon-image';
        if (/^(mp4|mov|avi|mkv|webm)$/.test(ext)) return 'icon-video';
        if (/^(zip|rar|7z|tar|gz)$/.test(ext)) return 'icon-file-unknown-fill';
        if (/^(doc|docx)$/.test(ext)) return 'icon-file-word-fill';
        if (/^(xls|xlsx|csv)$/.test(ext)) return 'icon-file-excel-fill';
        if (/^(ppt|pptx)$/.test(ext)) return 'icon-file-ppt-fill';
        if (/^pdf$/.test(ext)) return 'icon-file-text-fill';
        return 'icon-file-unknown-fill';
    }

    function fmtSize(n) {
        if (n < 1024) return n + ' B';
        if (n < 1048576) return (n / 1024).toFixed(1) + ' KB';
        return (n / 1048576).toFixed(2) + ' MB';
    }

    // 图片压缩：level 1~10，越大体积越小。
    // PNG 保持 PNG 格式（保留透明），按强度等比缩小尺寸；其余图片转 JPG 按质量压缩。
    function compressImage(file, level) {
        return new Promise(function (resolve) {
            var url = URL.createObjectURL(file);
            var img = new Image();
            img.onload = function () {
                var isPng = /^image\/png$/i.test(file.type);
                var canvas = document.createElement('canvas');
                var scale = 1 - (level - 1) / 9 * 0.4; // 1→1.0(原尺寸) 10→0.6
                canvas.width = Math.max(1, Math.round((img.naturalWidth || img.width) * scale));
                canvas.height = Math.max(1, Math.round((img.naturalHeight || img.height) * scale));
                canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
                URL.revokeObjectURL(url);
                if (isPng) {
                    canvas.toBlob(function (blob) {
                        if (blob) {
                            resolve(new File([blob], file.name.replace(/\.[^.]+$/, '') + '.png', { type: 'image/png' }));
                        } else {
                            resolve(file);
                        }
                    }, 'image/png');
                } else {
                    var quality = (11 - level) / 10; // 1→1.0(最清) 10→0.1(最小)
                    canvas.toBlob(function (blob) {
                        if (blob) {
                            resolve(new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' }));
                        } else {
                            resolve(file);
                        }
                    }, 'image/jpeg', quality);
                }
            };
            img.onerror = function () { URL.revokeObjectURL(url); resolve(file); };
            img.src = url;
        });
    }

    function initUpload(box) {
        if (!box || box.getAttribute(UP_KEY) === '1') return;
        box.setAttribute(UP_KEY, '1');

        var drop = box.querySelector('#upload-drop');
        var input = box.querySelector('#upload-input');
        var filesEl = box.querySelector('#upload-files');
        var compressEnable = box.querySelector('#compress-enable');
        var compressLevel = box.querySelector('#compress-level');
        var levelVal = box.querySelector('#compress-level-val');
        var countEl = box.querySelector('#upload-count');
        var confirmBtn = box.querySelector('#upload-confirm');
        var resultEl = box.querySelector('#upload-result');
        var uploadUrl = box.getAttribute('data-upload-url') || '/admin/attachment/upload';
        var target = box.getAttribute('data-target') || '';
        // 复用附件的请求条件：type 允许类型（逗号扩展名，*全部）、max 最大文件数（0 不限）
        var allowTypes = box.getAttribute('data-type') || '*';
        var maxCount = parseInt(box.getAttribute('data-max') || '0', 10);
        if (!drop || !input || !filesEl || !countEl || !confirmBtn) return;

        var files = [];
        var uploading = false;

        function extOf(name) {
            return (name.split('.').pop() || '').toLowerCase();
        }

        function allowExt(ext) {
            if (!allowTypes || allowTypes === '*') return true;
            var list = allowTypes.split(',');
            for (var t = 0; t < list.length; t++) {
                if ((list[t] || '').trim().toLowerCase() === ext) return true;
            }
            return false;
        }

        function syncCount() {
            var okCount = 0;
            var failCount = 0;
            files.forEach(function (it) {
                if (it.done && it.ok) okCount++;
                else if (it.done) failCount++;
            });
            if (!files.length) {
                countEl.textContent = '尚未选择文件';
            } else if (uploading) {
                countEl.textContent = '上传中...';
            } else {
                countEl.textContent = (failCount ? okCount + ' 成功 / ' + failCount + ' 失败' : '已上传 ' + okCount + ' 个文件');
            }
            var allDone = files.length > 0 && !uploading;
            confirmBtn.disabled = !allDone;
        }

        function render() {
            filesEl.innerHTML = '';
            files.forEach(function (it, idx) {
                var div = document.createElement('div');
                div.className = 'upload-file';
                div.innerHTML =
                    '<i class="bny-icon ' + it.icon + '"></i>' +
                    '<span class="upload-file-meta">' +
                    '  <span class="upload-file-name"></span>' +
                    '  <span class="upload-file-size"></span>' +
                    '</span>' +
                    '<span class="upload-file-status"></span>' +
                    '<button class="upload-file-remove" type="button">&times;</button>';
                div.querySelector('.upload-file-name').textContent = it.file.name;
                div.querySelector('.upload-file-size').textContent = fmtSize(it.file.size);
                div.querySelector('.upload-file-remove').addEventListener('click', function () {
                    if (uploading) return;
                    files.splice(idx, 1);
                    render();
                    syncCount();
                });
                filesEl.appendChild(div);
                it.el = div;
            });
            if (resultEl) resultEl.textContent = '';
        }

        function addFiles(list) {
            var added = 0;
            var skipped = 0;
            for (var i = 0; i < list.length; i++) {
                var f = list[i];
                // 类型白名单
                if (!allowExt(extOf(f.name))) {
                    skipped++;
                    continue;
                }
                // 数量上限
                if (maxCount > 0 && files.length + added >= maxCount) {
                    if (window.bny && bny.alert) bny.alert('最多只能上传 ' + maxCount + ' 个文件', 3);
                    break;
                }
                files.push({ file: f, icon: fileIcon(f.name), el: null, done: false, ok: false, path: '' });
                added++;
            }
            if (skipped) {
                if (window.bny && bny.alert) bny.alert('已跳过 ' + skipped + ' 个不支持类型的文件', 3);
            }
            render();
            syncCount();
            // 选好文件立即自动压缩上传
            if (added) startUpload();
        }

        // 自动压缩上传：逐文件压缩 → 上传，状态实时更新
        function startUpload() {
            if (uploading) return;
            uploading = true;
            syncCount();

            function next(i) {
                if (i >= files.length) {
                    uploading = false;
                    if (resultEl) {
                        var okCount = 0;
                        files.forEach(function (it) { if (it.done && it.ok) okCount++; });
                        resultEl.textContent = okCount ? '上传完成，可点击"确定"返回' : '';
                    }
                    syncCount();
                    return;
                }
                var it = files[i];
                var st = it.el.querySelector('.upload-file-status');
                var doUpload = function (f) {
                    st.textContent = '上传中...';
                    st.className = 'upload-file-status';
                    var fd = new FormData();
                    fd.append('file', f);
                    fd.append('type', allowTypes);
                    fetch(uploadUrl, { method: 'POST', body: fd })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            it.done = true;
                            if (data && data.code === 0) {
                                it.ok = true;
                                // 后端返回 url（优先）或 path
                                it.path = data.url || data.path || '';
                                st.textContent = '成功';
                                st.className = 'upload-file-status ok';
                            } else {
                                st.textContent = (data && data.msg) || '失败';
                                st.className = 'upload-file-status fail';
                            }
                            next(i + 1);
                        })
                        .catch(function () {
                            it.done = true;
                            st.textContent = '请求失败';
                            st.className = 'upload-file-status fail';
                            next(i + 1);
                        });
                };
                // 开启压缩且为图片 → 压缩后上传；否则原样上传
                var level = compressEnable && compressEnable.checked ? parseInt(compressLevel.value, 10) : 0;
                if (level > 0 && /^image\//.test(it.file.type)) {
                    st.textContent = '压缩中...';
                    st.className = 'upload-file-status';
                    compressImage(it.file, level).then(doUpload);
                } else {
                    doUpload(it.file);
                }
            }

            next(0);
        }

        // 确定：把成功上传的数据返回 input 并关闭弹层
        function fillBack() {
            var okPaths = [];
            files.forEach(function (it) {
                if (it.done && it.ok && it.path) okPaths.push(it.path);
            });
            if (target && okPaths.length) {
                var field = document.getElementById(target);
                if (field) {
                    var cur = (field.value || '').trim();
                    var arr = cur ? cur.split(',') : [];
                    okPaths.forEach(function (p) {
                        if (arr.indexOf(p) === -1) arr.push(p);
                    });
                    field.value = arr.join(',');
                    try { field.dispatchEvent(new Event('input', { bubbles: true })); } catch (e) { }
                }
            }
            var page = box.closest('.bny-page');
            if (page) {
                var closeBtn = page.querySelector('.close-btn');
                if (closeBtn) closeBtn.click();
            }
        }

        // 点击选择
        drop.addEventListener('click', function () { input.click(); });
        input.addEventListener('change', function () {
            addFiles(input.files);
            input.value = '';
        });
        // 拖拽
        ['dragenter', 'dragover'].forEach(function (name) {
            drop.addEventListener(name, function (e) {
                e.preventDefault();
                e.stopPropagation();
                drop.classList.add('dragover');
            });
        });
        ['dragleave', 'drop'].forEach(function (name) {
            drop.addEventListener(name, function (e) {
                e.preventDefault();
                e.stopPropagation();
                drop.classList.remove('dragover');
                if (name === 'drop' && e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
                    addFiles(e.dataTransfer.files);
                }
            });
        });
        // 压缩强度显示
        if (compressLevel && levelVal) {
            compressLevel.addEventListener('input', function () {
                levelVal.textContent = compressLevel.value;
            });
        }
        // 确定 → 回填 + 关闭
        confirmBtn.addEventListener('click', fillBack);
    }

    function tryInit(root) {
        if (!root || !root.querySelector) return;
        var list = root.classList && root.classList.contains('upload-box')
            ? [root]
            : Array.prototype.slice.call(root.querySelectorAll('.upload-box'));
        for (var i = 0; i < list.length; i++) initUpload(list[i]);
    }

    function watchUploads() {
        if (!window.MutationObserver || !document.body) return;
        new MutationObserver(function (mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var nodes = mutations[i].addedNodes;
                for (var j = 0; j < nodes.length; j++) {
                    if (nodes[j] && nodes[j].nodeType === 1) tryInit(nodes[j]);
                }
            }
        }).observe(document.body, { childList: true, subtree: true });
    }

    document.addEventListener('htmx:load', function (e) { tryInit(e.target); });
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { tryInit(document); watchUploads(); });
    } else {
        tryInit(document);
        watchUploads();
    }
})();
