
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
        var confirmBtn = box.querySelector('.attach-confirm');
        var empty = box.querySelector('.attach-empty');
        var items = Array.prototype.slice.call(grid.querySelectorAll('.attach-item'));

        var max = parseInt(box.getAttribute('data-max') || '0', 10);
        var target = box.getAttribute('data-target') || '';
        var typeFilter = box.getAttribute('data-type-filter') || '';

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

        // 类型过滤（如 type=image 时隐藏非图片）
        if (typeFilter) {
            for (var f = 0; f < items.length; f++) {
                if (items[f].getAttribute('data-type') !== typeFilter) {
                    items[f].style.display = 'none';
                }
            }
            if (empty) empty.style.display = visible().length ? 'none' : 'flex';
            allChecked();
        }

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
                        field.value = max === 1 ? picked[0] : picked.join(',');
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

    document.addEventListener('htmx:load', function (e) { tryInit(e.target); });
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { tryInit(document); });
    } else {
        tryInit(document);
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
