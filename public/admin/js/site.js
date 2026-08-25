
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
