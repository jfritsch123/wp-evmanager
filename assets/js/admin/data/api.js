/* ---------- AJAX Helper ---------- */
export function post(action, data) {
    return jQuery.ajax({
        url: WPEM.ajaxurl,
        method: 'POST',
        dataType: 'json',
        data: Object.assign({ action, _ajax_nonce: WPEM.nonce }, data || {})
    }).then(resp => {
        if (resp && resp.success) return resp.data;
        const msg = (resp && resp.data && resp.data.message) ? resp.data.message : 'Error';
        return $.Deferred().reject(msg).promise();
    });
}
