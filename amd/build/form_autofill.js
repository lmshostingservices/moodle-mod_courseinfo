define([], function() {
    'use strict';

    var debounceTimer = null;
    var apiUrl = '';

    function init(apiurl) {
        apiUrl = apiurl || 'https://lms-labs.com';

        var unitcodeField = document.getElementById('id_unitcode');
        var unittitleField = document.getElementById('id_unittitle');
        var nominalhoursField = document.getElementById('id_nominalhours');

        if (!unitcodeField || !nominalhoursField) {
            return;
        }

        injectLookupButton(unitcodeField, unittitleField, nominalhoursField);

        unitcodeField.addEventListener('blur', function() {
            var code = unitcodeField.value.trim();
            if (code.length >= 6) {
                lookupNominalHours(code, unittitleField, nominalhoursField);
            }
        });

        unitcodeField.addEventListener('input', function() {
            var code = unitcodeField.value.trim();
            if (debounceTimer) {
                clearTimeout(debounceTimer);
            }
            if (code.length >= 6) {
                debounceTimer = setTimeout(function() {
                    lookupNominalHours(code, unittitleField, nominalhoursField);
                }, 800);
            }
        });
    }

    function injectLookupButton(unitcodeField, unittitleField, nominalhoursField) {
        var existingBtn = document.getElementById('btn-lookup-nominalhours');
        if (existingBtn) {
            return;
        }

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.id = 'btn-lookup-nominalhours';
        btn.textContent = 'Lookup NCVER Hours';
        btn.style.cssText = [
            'margin-left:8px',
            'padding:3px 10px',
            'font-size:12px',
            'background:#1a73e8',
            'color:#fff',
            'border:none',
            'border-radius:4px',
            'cursor:pointer',
            'vertical-align:middle',
        ].join(';');

        btn.addEventListener('mouseenter', function() {
            btn.style.background = '#1557b0';
        });
        btn.addEventListener('mouseleave', function() {
            btn.style.background = '#1a73e8';
        });

        btn.addEventListener('click', function() {
            var code = unitcodeField.value.trim();
            if (!code) {
                showLookupStatus(nominalhoursField, 'Enter a unit code first (e.g. CHCCCS031).');
                setTimeout(function() { hideLookupStatus(nominalhoursField); }, 4000);
                return;
            }
            lookupNominalHours(code, unittitleField, nominalhoursField);
        });

        if (nominalhoursField.parentNode) {
            nominalhoursField.parentNode.appendChild(btn);
        }
    }

    function lookupNominalHours(unitcode, titleField, hoursField) {
        var code = unitcode.toUpperCase().replace(/\s+/g, '');
        var url = apiUrl + '/api/moodle/course-info/nominal-hours/' + encodeURIComponent(code);

        showLookupStatus(hoursField, 'Looking up ' + code + '...');

        var btn = document.getElementById('btn-lookup-nominalhours');
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Looking up...';
        }

        var xhr = new XMLHttpRequest();
        xhr.open('GET', url, true);
        xhr.timeout = 15000;
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = 'Lookup NCVER Hours';
                }
                if (xhr.status === 200) {
                    try {
                        var data = JSON.parse(xhr.responseText);
                        if (titleField && data.unitTitle && !titleField.value.trim()) {
                            titleField.value = data.unitTitle;
                        }
                        if (data.success && data.nominalHours) {
                            hoursField.value = data.nominalHours;
                            var src = data.source === 'ncver' ? 'NCVER' : data.source === 'database' ? 'RTO Compliance' : 'NCVER';
                            showLookupStatus(hoursField, '\u2713 Found: ' + data.nominalHours + ' hours (' + src + ')');
                            setTimeout(function() { hideLookupStatus(hoursField); }, 5000);
                        } else {
                            var titleMsg = data.unitTitle ? ' (' + data.unitTitle + ')' : '';
                            showLookupStatus(hoursField, 'No NCVER hours found for ' + code + titleMsg + '. Enter manually.');
                            setTimeout(function() { hideLookupStatus(hoursField); }, 6000);
                        }
                    } catch (e) {
                        hideLookupStatus(hoursField);
                    }
                } else {
                    showLookupStatus(hoursField, 'Lookup failed. Enter hours manually.');
                    setTimeout(function() { hideLookupStatus(hoursField); }, 5000);
                }
            }
        };
        xhr.ontimeout = function() {
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Lookup NCVER Hours';
            }
            showLookupStatus(hoursField, 'Lookup timed out. Enter hours manually.');
            setTimeout(function() { hideLookupStatus(hoursField); }, 5000);
        };
        xhr.send();
    }

    function showLookupStatus(field, msg) {
        var statusId = 'nominalhours-lookup-status';
        var el = document.getElementById(statusId);
        if (!el) {
            el = document.createElement('div');
            el.id = statusId;
            el.style.cssText = 'font-size:12px;margin-top:4px;padding:4px 8px;border-radius:4px;display:inline-block;';
            if (field.parentNode) {
                var wrapper = document.createElement('div');
                field.parentNode.appendChild(wrapper);
                wrapper.appendChild(el);
            }
        }
        if (msg.indexOf('\u2713') === 0 || msg.indexOf('Found') === 0) {
            el.style.color = '#155724';
            el.style.backgroundColor = '#d4edda';
            el.style.border = '1px solid #c3e6cb';
        } else if (msg.indexOf('No NCVER') === 0 || msg.indexOf('Lookup failed') === 0 || msg.indexOf('timed out') >= 0) {
            el.style.color = '#856404';
            el.style.backgroundColor = '#fff3cd';
            el.style.border = '1px solid #ffeeba';
        } else {
            el.style.color = '#0c5460';
            el.style.backgroundColor = '#d1ecf1';
            el.style.border = '1px solid #bee5eb';
        }
        el.textContent = msg;
        el.style.display = 'inline-block';
    }

    function hideLookupStatus(field) {
        var el = document.getElementById('nominalhours-lookup-status');
        if (el) {
            el.style.display = 'none';
        }
    }

    return {
        init: init
    };
});
