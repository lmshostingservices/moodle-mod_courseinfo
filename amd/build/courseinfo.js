define([], function() {
    'use strict';

    var CI_VERSION = '1.0.43';
    var cmid = 0;
    var canManage = false;
    var hasContent = false;
    var enableVol = false;
    var isEditing = false;
    var originalHtml = '';
    var themePrimary = '#1a73e8';
    var themeSecondary = '#1a73e8';
    var savedRange = null;
    var heroColour = ''; /* persisted hero banner override colour */

    /* Custom boxes  -  array of {id, icon, heading, body, position} */
    var customBoxes = [];
    var editingBoxId = null; /* null = new box, string = editing existing */

    /* -- Colour helpers ---------------------------------------------------- */
    function tintHex(hex, strength) {
        var c = hex.replace('#', '');
        if (c.length !== 6) { return hex; }
        var r = Math.round(255 + (parseInt(c.slice(0,2),16) - 255) * strength);
        var g = Math.round(255 + (parseInt(c.slice(2,4),16) - 255) * strength);
        var b = Math.round(255 + (parseInt(c.slice(4,6),16) - 255) * strength);
        return '#' + r.toString(16).padStart(2,'0') + g.toString(16).padStart(2,'0') + b.toString(16).padStart(2,'0');
    }

    function applyThemeCssVars() {
        var border  = tintHex(themePrimary, 0.18);
        var surface = tintHex(themePrimary, 0.06);
        var hover   = tintHex(themePrimary, 0.12);
        /* BUG-CI-CSSVAR-SCAN: courseinfo-scan-results was omitted  -  CSS vars were never
           set on this container, so any CSS-var-based rules added to styles.css for the
           scan results panel would not resolve. Fixed: added to the forEach list. */
        ['courseinfo-generated','courseinfo-content','courseinfo-custom-boxes','courseinfo-volume','courseinfo-scan-results'].forEach(function(id) {
            var el = document.getElementById(id);
            if (!el) { return; }
            el.style.setProperty('--ci-border',    border);
            el.style.setProperty('--ci-surface',   surface);
            el.style.setProperty('--ci-hover',     hover);
            el.style.setProperty('--ci-primary',   themePrimary);
            el.style.setProperty('--ci-secondary', themeSecondary);
        });
    }

    /* -- Init -------------------------------------------------------------- */
    function init(cmId, manage, content, unitCode, nomHours, volEnabled, primaryColour, secondaryColour) {
        cmid          = cmId;
        canManage     = manage;
        hasContent    = content;
        enableVol     = !!volEnabled;
        themePrimary  = primaryColour  || '#1a73e8';
        themeSecondary = secondaryColour || primaryColour || '#1a73e8';

        applyThemeCssVars();

        /* BUG-CI-ICONBREAK: Fix any residual text in FA icon elements from already-stored
           generated HTML (echoed directly by PHP on page load). */
        fixFaIconsInDom(document.getElementById('courseinfo-generated'));

        /* BUG-CI-BLANK-CONTENT: Force-reveal any ci-reveal elements in already-stored
           page-load HTML. The stored HTML may contain <script> blocks with an
           IntersectionObserver that sets opacity:0 on .ci-reveal cards and fades
           them in on scroll. Even when those scripts DO execute at page load, this
           belt-and-suspenders ensures cards are always immediately visible. */
        var genElInit = document.getElementById('courseinfo-generated');
        if (genElInit) {
            genElInit.querySelectorAll('.ci-reveal').forEach(function(el) {
                el.style.setProperty('opacity', '1', 'important');
                el.style.setProperty('animation', 'none', 'important');
                el.style.setProperty('transform', 'none', 'important');
                el.style.setProperty('visibility', 'visible', 'important');
            });
        }

        /* Print button available to all users */
        var btnPrint = document.getElementById('btn-print');
        if (btnPrint) { btnPrint.addEventListener('click', printCourseInfo); }

        if (!canManage) { return; } /* Student  -  nothing more to do */

        /* Teacher button wiring */
        var btnScan    = document.getElementById('btn-scan');
        var btnGen     = document.getElementById('btn-generate');
        var btnAddBox  = document.getElementById('btn-add-box');
        var btnEdit    = document.getElementById('btn-edit');
        var btnSave    = document.getElementById('btn-save');
        var btnCancel  = document.getElementById('btn-cancel-edit');

        if (btnScan)   { btnScan.addEventListener('click', scanActivities); }
        if (btnGen)    { btnGen.addEventListener('click', checkCreditsAndGenerate); }
        if (btnAddBox) { btnAddBox.addEventListener('click', function() { openBoxModal(null); }); }
        if (btnEdit)   { btnEdit.addEventListener('click', function() { toggleEdit(true); }); }
        if (btnSave)   { btnSave.addEventListener('click', saveEdits); }
        if (btnCancel) { btnCancel.addEventListener('click', function() { toggleEdit(false); }); }

        /* Modal wiring */
        var modalClose  = document.getElementById('ci-box-modal-close');
        var modalCancel = document.getElementById('ci-box-cancel');
        var modalSave   = document.getElementById('ci-box-save');
        if (modalClose)  { modalClose.addEventListener('click',  closeBoxModal); }
        if (modalCancel) { modalCancel.addEventListener('click', closeBoxModal); }
        if (modalSave)   { modalSave.addEventListener('click',   commitBox); }

        /* Close modal on backdrop click */
        var modal = document.getElementById('ci-box-modal');
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) { closeBoxModal(); }
            });
        }

        /* Build icon picker */
        buildIconPicker();

        /* Load custom boxes */
        loadBoxes();

        /* Hero colour picker */
        var btnHeroColor = document.getElementById('btn-hero-color');
        if (btnHeroColor) {
            /* live preview while dragging */
            btnHeroColor.addEventListener('input', function() { applyHeroColour(this.value); });
            /* persist on final pick */
            btnHeroColor.addEventListener('change', function() { saveHeroColor(this.value); });
        }

        /* Load persisted hero colour (applies to already-stored content shown on page load) */
        loadHeroColor();

        /* BUG-CI-HEROUI: Add floating "Change Colour" button directly ON the hero banner
           so teachers can click the banner itself  -  not hunt for the toolbar picker. */
        addHeroEditOverlay();
    }

    /* -- Selection helpers for colour pickers ------------------------------ */
    function saveSelection() {
        var sel = window.getSelection();
        if (sel && sel.rangeCount > 0) { savedRange = sel.getRangeAt(0).cloneRange(); }
    }
    function restoreSelection() {
        if (savedRange) {
            var sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(savedRange);
        }
    }

    /* -- Custom boxes: load / render / persist ----------------------------- */
    function loadBoxes() {
        ajaxCall('getboxes', function(err, data) {
            if (err || !data.success) { return; }
            customBoxes = data.boxes || [];
            renderCustomBoxes();
        });
    }

    /* -- Hero banner colour: load / apply / persist ------------------------ */
    function loadHeroColor() {
        ajaxCall('getherocolor', function(err, data) {
            if (!err && data && data.success && data.color) {
                heroColour = data.color;
                applyHeroColour(heroColour);
                /* Sync the picker UI to the saved value */
                var picker = document.getElementById('btn-hero-color');
                if (picker) { picker.value = heroColour; }
            }
        });
    }

    function applyHeroColour(colour) {
        if (!colour) { return; }
        var generated = document.getElementById('courseinfo-generated');
        if (!generated) { return; }
        /* Target .ci-header  -  the hero banner div the AI wraps the document in */
        var header = generated.querySelector('.ci-header');
        if (header) {
            header.style.background = colour;
            header.style.backgroundImage = 'none'; /* kill any linear-gradient */
        }
    }

    function saveHeroColor(colour) {
        heroColour = colour;
        applyHeroColour(colour);
        var xhr = new XMLHttpRequest();
        xhr.open('POST', M.cfg.wwwroot + '/mod/courseinfo/ajax.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.send('action=saveherocolor&cmid=' + cmid + '&sesskey=' + M.cfg.sesskey + '&color=' + encodeURIComponent(colour));
    }

    /* BUG-CI-HEROUI: Add a floating "Change Colour" button on the hero banner so
       teachers can click it directly instead of hunting for the toolbar picker.
       Triggered after loadHeroColor() so it runs on both page-load and post-generation. */
    function addHeroEditOverlay() {
        if (!canManage) { return; }
        var generated = document.getElementById('courseinfo-generated');
        if (!generated) { return; }
        var header = generated.querySelector('.ci-header');
        if (!header) { return; }

        /* Remove any previous overlay (e.g. after regeneration) */
        var existing = header.querySelector('#ci-hero-edit-btn');
        if (existing) { existing.parentNode.removeChild(existing); }

        /* Ensure the header can contain an absolutely-positioned child */
        var pos = window.getComputedStyle(header).position;
        if (pos === 'static') { header.style.position = 'relative'; }

        var btn = document.createElement('button');
        btn.id = 'ci-hero-edit-btn';
        btn.title = 'Click to change hero banner colour';
        btn.type = 'button';
        btn.style.cssText = [
            'position:absolute', 'top:12px', 'right:12px',
            'background:rgba(255,255,255,0.22)',
            'border:1px solid rgba(255,255,255,0.55)',
            'border-radius:4px',
            'padding:4px 10px',
            'color:#fff',
            'font-size:12px',
            'cursor:pointer',
            'display:flex',
            'align-items:center',
            'gap:5px',
            'z-index:20',
            'white-space:nowrap',
            'backdrop-filter:blur(4px)',
            '-webkit-backdrop-filter:blur(4px)',
        ].join(';');
        btn.innerHTML = '<i class="fa fa-paint-brush" style="font-weight:900;font-size:11px;"></i> Change Colour';

        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            var picker = document.getElementById('btn-hero-color');
            if (picker) { picker.click(); }
        });

        header.appendChild(btn);
    }

    function persistBoxes(callback) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', M.cfg.wwwroot + '/mod/courseinfo/ajax.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && callback) {
                try { callback(null, JSON.parse(xhr.responseText)); }
                catch(e) { callback('Save error'); }
            }
        };
        xhr.send('action=saveboxes&cmid=' + cmid + '&sesskey=' + M.cfg.sesskey + '&boxes=' + encodeURIComponent(JSON.stringify(customBoxes)));
    }

    function renderCustomBoxes() {
        var container = document.getElementById('courseinfo-custom-boxes');
        if (!container) { return; }
        container.innerHTML = '';

        if (!customBoxes || !customBoxes.length) { return; }

        /* Sort by position */
        var sorted = customBoxes.slice().sort(function(a, b) { return a.position - b.position; });

        sorted.forEach(function(box) {
            container.appendChild(buildBoxCard(box));
        });

        applyThemeCssVars();
    }

    function buildBoxCard(box) {
        var border  = tintHex(themePrimary, 0.18);
        var surface = tintHex(themePrimary, 0.06);

        var card = document.createElement('div');
        card.className = 'ci-card ci-custom-box';
        card.setAttribute('data-box-id', box.id);
        /* BUG-CI-BOXCSS: Full border only  -  no single-sided border on rounded element */
        card.style.cssText = [
            'background:#fff',
            'border:1px solid ' + border,
            'border-radius:12px',
            'padding:28px 32px',
            'margin-bottom:24px',
            'position:relative',
            'box-shadow:0 2px 8px rgba(0,0,0,0.07)',
        ].join(';');

        /* Move/edit/delete controls  -  visible only to teachers */
        if (canManage) {
            var ctrl = document.createElement('div');
            ctrl.className = 'ci-box-controls';
            ctrl.style.cssText = 'position:absolute;top:12px;right:12px;display:flex;gap:6px;';

            function mkCtrlBtn(icon, title, handler) {
                var b = document.createElement('button');
                b.type = 'button';
                b.title = title;
                b.innerHTML = '<i class="fa ' + icon + '"></i>';
                b.style.cssText = 'border:1px solid #d0d7de;background:#f6f8fa;border-radius:5px;padding:4px 7px;cursor:pointer;font-size:12px;color:#57606a;';
                b.addEventListener('mouseover', function() { this.style.background = '#eef0f3'; });
                b.addEventListener('mouseout',  function() { this.style.background = '#f6f8fa'; });
                b.addEventListener('click', handler);
                return b;
            }

            ctrl.appendChild(mkCtrlBtn('fa-arrow-up',   'Move up',   function() { moveBox(box.id, -1); }));
            ctrl.appendChild(mkCtrlBtn('fa-arrow-down', 'Move down', function() { moveBox(box.id, 1); }));
            ctrl.appendChild(mkCtrlBtn('fa-pencil',     'Edit box',  function() { openBoxModal(box.id); }));
            ctrl.appendChild(mkCtrlBtn('fa-trash',      'Delete box',function() {
                if (window.confirm('Delete this box?')) { deleteBox(box.id); }
            }));
            card.appendChild(ctrl);
        }

        /* Icon + heading */
        var heading = document.createElement('div');
        heading.style.cssText = 'display:flex;align-items:center;gap:12px;margin-bottom:12px;' + (canManage ? 'padding-right:160px;' : '');

        var iconCircle = document.createElement('div');
        iconCircle.className = 'ci-icon';
        iconCircle.style.cssText = 'width:40px;height:40px;border-radius:50%;background:' + tintHex(themePrimary, 0.12) + ';display:flex;align-items:center;justify-content:center;flex-shrink:0;';
        iconCircle.innerHTML = '<i class="fa ' + escapeHtml(box.icon) + '" style="font-size:18px;color:' + themePrimary + ';"></i>';

        var headingText = document.createElement('h3');
        headingText.style.cssText = 'margin:0;font-size:1.1em;font-weight:700;color:#1e293b;';
        headingText.textContent = box.heading || 'Custom Section';

        heading.appendChild(iconCircle);
        heading.appendChild(headingText);
        card.appendChild(heading);

        /* Body */
        var body = document.createElement('div');
        body.style.cssText = 'font-size:14.5px;line-height:1.7;color:#475569;';
        body.innerHTML = box.body || '';
        card.appendChild(body);

        return card;
    }

    function moveBox(boxId, direction) {
        /* BUG-CI-BOXMOVE: Must work on VISUAL (sorted) order, not unsorted array order.
           customBoxes.findIndex() returns an unsorted-array index. If that index is at
           the end of the unsorted array, targetIdx >= length triggers a silent early
           return even when the box is not visually at the boundary. Fix: find the visual
           (sorted) position first, then locate the two boxes by ID in the original array
           and swap only their position values. */
        var sorted = customBoxes.slice().sort(function(a, b) { return a.position - b.position; });

        var visualIdx = -1;
        for (var i = 0; i < sorted.length; i++) {
            if (sorted[i].id === boxId) { visualIdx = i; break; }
        }
        if (visualIdx < 0) { return; }

        var targetVisualIdx = visualIdx + direction;
        if (targetVisualIdx < 0 || targetVisualIdx >= sorted.length) { return; }

        /* Find both boxes in the original (unsorted) array by ID and swap positions */
        var idA = sorted[visualIdx].id;
        var idB = sorted[targetVisualIdx].id;
        var boxA = null, boxB = null;
        for (var j = 0; j < customBoxes.length; j++) {
            if (customBoxes[j].id === idA) { boxA = customBoxes[j]; }
            if (customBoxes[j].id === idB) { boxB = customBoxes[j]; }
        }
        if (!boxA || !boxB) { return; }

        var tmp = boxA.position;
        boxA.position = boxB.position;
        boxB.position = tmp;

        /* Re-normalise to clean sequential integers */
        customBoxes.sort(function(a, b) { return a.position - b.position; });
        customBoxes.forEach(function(b, i) { b.position = i * 10; });

        persistBoxes(function() { renderCustomBoxes(); });
    }

    function deleteBox(boxId) {
        customBoxes = customBoxes.filter(function(b) { return b.id !== boxId; });
        persistBoxes(function() { renderCustomBoxes(); });
    }

    /* -- Box modal: open / close / commit ---------------------------------- */
    var ICONS = [
        'fa-info-circle','fa-star','fa-check-circle','fa-exclamation-triangle',
        'fa-lightbulb-o','fa-book','fa-graduation-cap','fa-pencil',
        'fa-comments','fa-users','fa-user','fa-heart',
        'fa-thumbs-up','fa-trophy','fa-certificate','fa-shield',
        'fa-lock','fa-unlock','fa-globe','fa-map-marker',
        'fa-clock-o','fa-calendar','fa-bell','fa-envelope',
        'fa-phone','fa-laptop','fa-desktop','fa-mobile',
        'fa-file-text','fa-folder','fa-download','fa-upload',
        'fa-cog','fa-wrench','fa-search','fa-eye',
        'fa-bullhorn','fa-flag','fa-tag','fa-list',
        'fa-table','fa-bar-chart','fa-pie-chart','fa-line-chart',
        'fa-money','fa-credit-card','fa-bank','fa-briefcase',
        'fa-medkit','fa-stethoscope','fa-home','fa-building',
    ];

    function buildIconPicker() {
        var picker = document.getElementById('ci-icon-picker');
        if (!picker) { return; }
        picker.innerHTML = '';
        ICONS.forEach(function(icon) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.title = icon;
            btn.setAttribute('data-icon', icon);
            btn.innerHTML = '<i class="fa ' + icon + '"></i>';
            btn.style.cssText = 'width:36px;height:36px;border:2px solid transparent;border-radius:6px;background:' + tintHex(themePrimary, 0.08) + ';color:' + themePrimary + ';cursor:pointer;font-size:15px;display:flex;align-items:center;justify-content:center;transition:all 0.15s;';
            btn.addEventListener('click', function() {
                document.getElementById('ci-box-icon').value = icon;
                picker.querySelectorAll('button').forEach(function(b) {
                    b.style.borderColor = 'transparent';
                    b.style.background  = tintHex(themePrimary, 0.08);
                    b.style.color       = themePrimary;
                });
                btn.style.borderColor = themePrimary;
                btn.style.background  = tintHex(themePrimary, 0.10);
                btn.style.color       = themePrimary;
            });
            picker.appendChild(btn);
        });
    }

    function populatePositionDropdown() {
        /* BUG-CI-BOXPLACE: The old dropdown mapped AI section headings to position values
           (10, 20, 30...) that exactly collided with normalised custom-box positions
           (0, 10, 20...). Any new box at position=10 tied with an existing box at
           position=10  -  JS sort is not stable on ties, so the new box landed randomly.
           Fix: base the dropdown on the EXISTING CUSTOM BOX ORDER and use offset values
           that cannot collide (-5 for top, box.position+5 for after-N, 9999 for bottom).
           Note: custom boxes are all inside #courseinfo-custom-boxes which sits after
           the AI-generated content  -  interleaving with AI sections is not supported. */
        var sel = document.getElementById('ci-box-position');
        if (!sel) { return; }
        sel.innerHTML = '';

        /* Sorted other boxes (exclude the one currently being edited) */
        var others = customBoxes
            .filter(function(b) { return b.id !== editingBoxId; })
            .slice()
            .sort(function(a, b) { return a.position - b.position; });

        function addOpt(val, label) {
            var o = document.createElement('option');
            o.value = val;
            o.textContent = label;
            sel.appendChild(o);
        }

        if (others.length === 0) {
            addOpt(0, 'Only position');
            return;
        }

        addOpt(-5, 'First  -  before all boxes');
        others.forEach(function(box, i) {
            /* +5 ensures the new box sorts AFTER this one without colliding */
            addOpt(box.position + 5, 'After: ' + (box.heading || 'Box ' + (i + 1)));
        });
        addOpt(9999, 'Last  -  after all boxes');
    }

    function openBoxModal(boxId) {
        editingBoxId = boxId;
        var box = boxId ? customBoxes.find(function(b) { return b.id === boxId; }) : null;

        /* Title */
        var titleEl = document.getElementById('ci-box-modal-title');
        if (titleEl) { titleEl.textContent = box ? 'Edit Custom Box' : 'Add Custom Box'; }

        /* Set icon */
        var icon = (box && box.icon) ? box.icon : 'fa-info-circle';
        document.getElementById('ci-box-icon').value = icon;
        var picker = document.getElementById('ci-icon-picker');
        if (picker) {
            picker.querySelectorAll('button').forEach(function(b) {
                var isSelected = b.getAttribute('data-icon') === icon;
                b.style.borderColor = isSelected ? themePrimary : 'transparent';
                b.style.background  = tintHex(themePrimary, isSelected ? 0.10 : 0.08);
                b.style.color       = themePrimary;
            });
        }

        /* Set heading */
        var headingEl = document.getElementById('ci-box-heading');
        if (headingEl) { headingEl.value = box ? (box.heading || '') : ''; }

        /* Set body */
        var bodyEl = document.getElementById('ci-box-body');
        if (bodyEl) { bodyEl.innerHTML = box ? (box.body || '') : ''; }

        /* Build inline RTE toolbar for the modal body */
        buildModalRteToolbar();

        /* Populate position dropdown */
        populatePositionDropdown();
        var posSel = document.getElementById('ci-box-position');
        if (posSel && box) {
            /* Select closest position */
            var opts = Array.from(posSel.options);
            var best = opts.reduce(function(prev, curr) {
                return Math.abs(parseInt(curr.value) - box.position) < Math.abs(parseInt(prev.value) - box.position) ? curr : prev;
            });
            if (best) { best.selected = true; }
        }

        /* Show modal */
        var modal = document.getElementById('ci-box-modal');
        if (modal) { modal.style.display = 'block'; document.body.style.overflow = 'hidden'; }
    }

    function closeBoxModal() {
        var modal = document.getElementById('ci-box-modal');
        if (modal) { modal.style.display = 'none'; document.body.style.overflow = ''; }
        editingBoxId = null;
    }

    function commitBox() {
        var icon     = document.getElementById('ci-box-icon').value    || 'fa-info-circle';
        var heading  = document.getElementById('ci-box-heading').value  || '';
        var body     = document.getElementById('ci-box-body').innerHTML || '';
        var position = parseInt(document.getElementById('ci-box-position').value) || 0;

        if (!heading.trim()) {
            var h = document.getElementById('ci-box-heading');
            if (h) { h.style.borderColor = 'red'; h.focus(); }
            return;
        }
        var h = document.getElementById('ci-box-heading');
        if (h) { h.style.borderColor = ''; }

        if (editingBoxId) {
            /* Update existing */
            var box = customBoxes.find(function(b) { return b.id === editingBoxId; });
            if (box) { box.icon = icon; box.heading = heading; box.body = body; box.position = position; }
        } else {
            /* New box */
            customBoxes.push({
                id: 'box-' + Date.now(),
                icon: icon,
                heading: heading,
                body: body,
                position: position,
            });
        }

        /* Re-normalise positions to avoid gaps */
        customBoxes.sort(function(a, b) { return a.position - b.position; });
        customBoxes.forEach(function(b, i) { b.position = i * 10; });

        persistBoxes(function(err) {
            if (!err) { renderCustomBoxes(); closeBoxModal(); }
        });
    }

    /* -- RTE toolbar for modal body editor --------------------------------- */
    function buildModalRteToolbar() {
        var container = document.getElementById('ci-box-rte-toolbar');
        if (!container) { return; }
        container.innerHTML = '';

        var bodyEl = document.getElementById('ci-box-body');

        var tb = document.createElement('div');
        tb.style.cssText = [
            'background:#fff', 'border:1px solid #d0d7de',
            'border-radius:8px 8px 0 0', 'padding:6px 8px',
            'display:flex', 'flex-wrap:wrap', 'align-items:center', 'gap:2px',
            'user-select:none',
        ].join(';');

        tb.addEventListener('mousedown', function(e) {
            if (e.target.type === 'color') { return; }
            e.preventDefault();
        });

        /* Update body border to connect with toolbar */
        if (bodyEl) { bodyEl.style.borderTop = 'none'; bodyEl.style.borderRadius = '0 0 8px 8px'; }

        function divider() {
            var d = document.createElement('span');
            d.style.cssText = 'display:inline-block;width:1px;height:20px;background:#d0d7de;margin:0 3px;flex-shrink:0;';
            return d;
        }

        var btnBase = 'border:1px solid transparent;background:transparent;border-radius:4px;padding:3px 6px;cursor:pointer;font-size:12px;line-height:1;color:#24292f;';

        function mkBtn(html, title, fn) {
            var b = document.createElement('button');
            b.type = 'button';
            b.title = title;
            b.innerHTML = html;
            b.style.cssText = btnBase;
            b.addEventListener('mouseover', function() { this.style.background = '#f0f3f6'; this.style.borderColor = '#d0d7de'; });
            b.addEventListener('mouseout',  function() { this.style.background = 'transparent'; this.style.borderColor = 'transparent'; });
            b.addEventListener('click', function() { bodyEl && bodyEl.focus(); fn(); });
            return b;
        }
        function execBtn(html, title, cmd, val) {
            return mkBtn(html, title, function() { document.execCommand(cmd, false, val || null); });
        }

        tb.appendChild(execBtn('<b>B</b>', 'Bold',          'bold'));
        tb.appendChild(execBtn('<i>I</i>', 'Italic',        'italic'));
        tb.appendChild(execBtn('<u>U</u>', 'Underline',     'underline'));
        tb.appendChild(divider());
        tb.appendChild(execBtn('<i class="fa fa-align-left"></i>',   'Align left',   'justifyLeft'));
        tb.appendChild(execBtn('<i class="fa fa-align-center"></i>', 'Centre',       'justifyCenter'));
        tb.appendChild(execBtn('<i class="fa fa-align-right"></i>',  'Align right',  'justifyRight'));
        tb.appendChild(divider());
        tb.appendChild(execBtn('<i class="fa fa-list-ul"></i>', 'Bullet list',   'insertUnorderedList'));
        tb.appendChild(execBtn('<i class="fa fa-list-ol"></i>', 'Numbered list', 'insertOrderedList'));
        tb.appendChild(divider());

        /* Text colour */
        var txtInput = document.createElement('input');
        txtInput.type = 'color';
        txtInput.value = '#000000';
        txtInput.title = 'Text colour';
        var txtWrap = document.createElement('label');
        txtWrap.title = 'Text colour';
        txtWrap.style.cssText = 'display:flex;flex-direction:column;align-items:center;gap:1px;cursor:pointer;position:relative;';
        var txtLbl = document.createElement('span');
        txtLbl.style.cssText = 'font-size:10px;font-weight:700;line-height:1;color:#24292f;';
        txtLbl.textContent = 'A';
        var txtBar = document.createElement('span');
        txtBar.style.cssText = 'width:14px;height:2px;border-radius:1px;background:#000;display:block;';
        txtInput.addEventListener('mousedown', saveSelection);
        txtInput.addEventListener('input', function() { txtBar.style.background = this.value; restoreSelection(); document.execCommand('foreColor', false, this.value); });
        txtInput.style.cssText = 'position:absolute;opacity:0;width:100%;height:100%;top:0;left:0;cursor:pointer;';
        txtWrap.appendChild(txtLbl);
        txtWrap.appendChild(txtBar);
        txtWrap.appendChild(txtInput);
        tb.appendChild(txtWrap);

        tb.appendChild(divider());
        tb.appendChild(mkBtn('<i class="fa fa-eraser"></i>', 'Remove formatting', function() { document.execCommand('removeFormat'); }));

        container.appendChild(tb);
    }

    /* -- Floating RTE toolbar for AI content editing ----------------------- */
    function buildRteToolbar() {
        var tb = document.createElement('div');
        tb.id = 'ci-rte-toolbar';
        tb.style.cssText = [
            'position:sticky', 'top:0', 'z-index:9999',
            'background:#fff', 'border:1px solid #d0d7de',
            'border-radius:8px', 'padding:7px 10px',
            'margin-bottom:10px',
            'display:flex', 'flex-wrap:wrap', 'align-items:center', 'gap:3px',
            'box-shadow:0 4px 14px rgba(0,0,0,0.13)', 'user-select:none',
        ].join(';');

        tb.addEventListener('mousedown', function(e) {
            if (e.target.type === 'color') { return; }
            e.preventDefault();
        });

        function divider() {
            var d = document.createElement('span');
            d.style.cssText = 'display:inline-block;width:1px;height:22px;background:#d0d7de;margin:0 4px;flex-shrink:0;';
            return d;
        }

        var btnBase = ['border:1px solid transparent','background:transparent','border-radius:5px','padding:4px 7px','cursor:pointer','font-size:13px','line-height:1','color:#24292f'].join(';');

        function mkBtn(html, title, fn) {
            var b = document.createElement('button');
            b.type = 'button'; b.title = title; b.innerHTML = html; b.style.cssText = btnBase;
            b.addEventListener('mouseover', function() { this.style.background = '#f0f3f6'; this.style.borderColor = '#d0d7de'; });
            b.addEventListener('mouseout',  function() { this.style.background = 'transparent'; this.style.borderColor = 'transparent'; });
            b.addEventListener('click', fn);
            return b;
        }
        function execBtn(html, title, cmd, val) {
            return mkBtn(html, title, function() { document.execCommand(cmd, false, val || null); });
        }

        /* Format / size selectors */
        var fmtSel = document.createElement('select');
        fmtSel.title = 'Text style';
        fmtSel.style.cssText = 'border:1px solid #d0d7de;background:#f6f8fa;border-radius:5px;padding:4px 6px;font-size:12px;cursor:pointer;max-width:110px;';
        [['Paragraph','p'],['Heading 2','h2'],['Heading 3','h3'],['Heading 4','h4'],['Preformatted','pre']].forEach(function(o) {
            var opt = document.createElement('option'); opt.value = o[1]; opt.textContent = o[0]; fmtSel.appendChild(opt);
        });
        fmtSel.addEventListener('change', function() { document.execCommand('formatBlock', false, '<' + this.value + '>'); this.value = 'p'; });

        var szSel = document.createElement('select');
        szSel.title = 'Font size';
        szSel.style.cssText = 'border:1px solid #d0d7de;background:#f6f8fa;border-radius:5px;padding:4px 6px;font-size:12px;cursor:pointer;max-width:80px;';
        [['Small','1'],['Normal','3'],['Large','4'],['X-Large','5'],['XX-Large','6']].forEach(function(o) {
            var opt = document.createElement('option'); opt.value = o[1]; opt.textContent = o[0]; if (o[0]==='Normal') { opt.selected=true; } szSel.appendChild(opt);
        });
        szSel.addEventListener('change', function() { document.execCommand('fontSize', false, this.value); this.value = '3'; });

        tb.appendChild(fmtSel);
        tb.appendChild(szSel);
        tb.appendChild(divider());

        tb.appendChild(execBtn('<b style="font-size:14px;">B</b>', 'Bold',          'bold'));
        tb.appendChild(execBtn('<i style="font-size:14px;">I</i>', 'Italic',        'italic'));
        tb.appendChild(execBtn('<u style="font-size:14px;">U</u>', 'Underline',     'underline'));
        tb.appendChild(execBtn('<s style="font-size:13px;">S</s>', 'Strikethrough', 'strikeThrough'));
        tb.appendChild(divider());
        tb.appendChild(execBtn('<i class="fa fa-align-left"></i>',   'Align left',   'justifyLeft'));
        tb.appendChild(execBtn('<i class="fa fa-align-center"></i>', 'Centre',       'justifyCenter'));
        tb.appendChild(execBtn('<i class="fa fa-align-right"></i>',  'Align right',  'justifyRight'));
        tb.appendChild(divider());
        tb.appendChild(execBtn('<i class="fa fa-list-ul"></i>', 'Bullet list',   'insertUnorderedList'));
        tb.appendChild(execBtn('<i class="fa fa-list-ol"></i>', 'Numbered list', 'insertOrderedList'));
        tb.appendChild(divider());

        function colourPicker(label, bgLabel, title, cmd) {
            var input = document.createElement('input');
            input.type = 'color';
            input.value = label === 'A' ? '#000000' : '#ffff00';
            input.title = title;
            var wrap = document.createElement('label');
            wrap.title = title;
            wrap.style.cssText = 'display:flex;flex-direction:column;align-items:center;gap:1px;cursor:pointer;position:relative;';
            var lbl = document.createElement('span');
            lbl.style.cssText = 'font-size:11px;font-weight:700;line-height:1;color:#24292f;' + (bgLabel ? 'background:' + bgLabel + ';padding:0 2px;' : '');
            lbl.textContent = 'A';
            var bar = document.createElement('span');
            bar.style.cssText = 'width:16px;height:3px;border-radius:1px;background:' + input.value + ';display:block;';
            input.addEventListener('mousedown', saveSelection);
            input.addEventListener('input', function() { bar.style.background = this.value; restoreSelection(); document.execCommand(cmd, false, this.value); });
            input.style.cssText = 'position:absolute;opacity:0;width:100%;height:100%;top:0;left:0;cursor:pointer;';
            wrap.appendChild(lbl); wrap.appendChild(bar); wrap.appendChild(input);
            return wrap;
        }

        tb.appendChild(colourPicker('A', null,    'Text colour',      'foreColor'));
        tb.appendChild(colourPicker('A', '#ff0',  'Highlight colour', 'hiliteColor'));
        tb.appendChild(divider());
        tb.appendChild(mkBtn('<i class="fa fa-link"></i>',   'Insert link',  function() { var u=window.prompt('Enter URL:','https://'); if(u){document.execCommand('createLink',false,u);} }));
        tb.appendChild(mkBtn('<i class="fa fa-unlink"></i>', 'Remove link',  function() { document.execCommand('unlink'); }));
        tb.appendChild(divider());
        tb.appendChild(mkBtn('<i class="fa fa-eraser"></i><span style="font-size:11px;margin-left:4px;">Clear</span>', 'Remove formatting', function() { document.execCommand('removeFormat'); }));

        return tb;
    }

    /* -- Edit mode toggle -------------------------------------------------- */
    function toggleEdit(enable) {
        var contentEl = document.getElementById('courseinfo-generated');
        if (!contentEl) { return; }
        isEditing = enable;

        if (enable) {
            originalHtml = contentEl.innerHTML;
            contentEl.contentEditable = 'true';
            contentEl.style.outline      = '2px solid ' + themePrimary;
            contentEl.style.borderRadius = '4px';
            contentEl.style.minHeight    = '200px';
            var existing = document.getElementById('ci-rte-toolbar');
            if (!existing) { contentEl.parentNode.insertBefore(buildRteToolbar(), contentEl); }
            contentEl.focus();
        } else {
            contentEl.contentEditable = 'false';
            contentEl.style.outline      = '';
            contentEl.style.borderRadius = '';
            contentEl.style.minHeight    = '';
            var toolbar = document.getElementById('ci-rte-toolbar');
            if (toolbar) { toolbar.parentNode.removeChild(toolbar); }
            savedRange = null;
        }

        setVisible('btn-edit',       !enable);
        setVisible('btn-print',      !enable);
        setVisible('btn-generate',   !enable);
        setVisible('btn-scan',       !enable);
        setVisible('btn-add-box',    !enable);
        setVisible('btn-save',        enable);
        setVisible('btn-cancel-edit', enable);
    }

    /* -- Utility ----------------------------------------------------------- */
    function setVisible(id, visible) {
        var el = document.getElementById(id);
        if (el) { el.style.display = visible ? '' : 'none'; }
    }

    function showStatus(msg) {
        var s = document.getElementById('courseinfo-status');
        var t = document.getElementById('courseinfo-status-text');
        if (s) { s.style.display = ''; s.className = 'alert alert-info mb-3'; }
        if (t) { t.textContent = msg; }
    }

    function hideStatus() {
        var el = document.getElementById('courseinfo-status');
        if (el) { el.style.display = 'none'; }
    }

    function showError(msg) {
        var el = document.getElementById('courseinfo-status');
        if (el) { el.style.display = ''; el.className = 'alert alert-danger mb-3'; el.innerHTML = '<i class="fa fa-exclamation-triangle mr-2"></i> ' + msg; }
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /* -- BUG-CI-HTMLSTRIP / BUG-CI-MAXWIDTH: Sanitize AI-generated HTML ------
       Strips document-level tags (<html>, <head>, <body> etc.) that break Moodle
       when injected into a div, and removes mobile-causing max-width constraints. */
    function sanitizeGeneratedHtml(html) {
        if (!html) { return html; }
        /* Strip document-level tags */
        html = html.replace(/<!DOCTYPE[^>]*>/gi, '');
        html = html.replace(/<html[^>]*>/gi, '');
        html = html.replace(/<\/html>/gi, '');
        html = html.replace(/<head[^>]*>[\s\S]*?<\/head>/gi, '');
        html = html.replace(/<body[^>]*>/gi, '');
        html = html.replace(/<\/body>/gi, '');
        html = html.replace(/<meta[^>]*/gi, '');
        /* BUG-CI-SCRIPT-STRIP: Strip <script> blocks from generated HTML.
           Browsers do NOT execute scripts injected via innerHTML (security policy).
           The AI generates IntersectionObserver scroll-reveal code that sets
           opacity:0 on .ci-reveal cards and fades them in when scrolled into view.
           Since the script never runs, all ci-reveal cards stay invisible (blank
           content below the hero header). Stripping the script blocks also prevents
           DevTools CSP/security warnings about non-executing inline scripts. */
        html = html.replace(/<script[^>]*>[\s\S]*?<\/script>/gi, '');
        /* BUG-CI-BANNERWIDTH / BUG-CI-MAXWIDTH: Strip ANY px-based max-width combined
           with margin:0 auto  -  catches 800px, 900px, 1000px, 1200px etc., not just 900px. */
        html = html.replace(/max-width\s*:\s*\d+(?:\.\d+)?px\s*;?\s*margin\s*:\s*0\s+auto/gi, 'width:100%');
        html = html.replace(/margin\s*:\s*0\s+auto\s*;?\s*max-width\s*:\s*\d+(?:\.\d+)?px/gi, 'width:100%');
        /* BUG-CI-BOXWIDTH: Strip negative left/right margins that bleed outside Bootstrap
           column padding and make cards appear wider than the toolbar. */
        html = html.replace(/margin-left\s*:\s*-[\d.]+[a-z%]*/gi, 'margin-left:0');
        html = html.replace(/margin-right\s*:\s*-[\d.]+[a-z%]*/gi, 'margin-right:0');
        /* BUG-CI-ICONBREAK: Strip text content from FA icon elements.
           AI sometimes generates <i class="fa fa-*">icontext</i> (Material Icons style)
           instead of the correct empty <i class="fa fa-*"></i>. Strip the text. */
        html = html.replace(/<i([^>]*class="[^"]*\bfa\b[^"]*"[^>]*)>([^<]*)<\/i>/gi, '<i$1></i>');
        /* BUG-CI-ICONBREAK-STYLE: Strip font-family from ALL CSS rules in ALL generated
           <style> blocks  -  not just those with #courseinfo-generated prefix.
           Root cause: AI generates unscoped rules like "* { font-family: 'Inter' }" or
           ".ci-card i { font-family: inherit }" that survive the old narrow regex.
           When injected into the DOM these <style> blocks become globally active CSS
           and can override FA font-family even on elements our CSS fix targets.
           Stripping font-family from ALL generated CSS is safe: Moodle theme provides
           the correct body font via inheritance; FA font is forced by fixFaIconsInDom(). */
        html = html.replace(/(<style[^>]*>)([\s\S]*?)(<\/style>)/gi, function(match, open, css, close) {
            css = css.replace(/font-family\s*:[^;!}]*(!important)?\s*;?/gi, '');
            return open + css + close;
        });
        return html.trim();
    }

    /* BUG-CI-ICONBREAK: After injecting generated HTML into the DOM, strip any residual
       text nodes from Font Awesome <i> elements that survived string-level sanitization
       (e.g. entities, CDATA, or multi-line text). Also forces FontAwesome font via
       el.style.setProperty with 'important' priority  -  this beats ALL CSS rules
       including !important stylesheet rules, regardless of specificity or source order.
       This is the definitive fix: no AI-generated <style> block can override an
       inline !important set via JS setProperty. Runs on init (for stored HTML) and
       immediately after every innerHTML assignment (for freshly generated HTML). */
    function fixFaIconsInDom(containerEl) {
        if (!containerEl) { return; }
        containerEl.querySelectorAll('i.fa, i[class*="fa-"], span.fa').forEach(function(el) {
            /* Strip all direct text node children (AI sometimes fills FA elements with text) */
            Array.prototype.slice.call(el.childNodes).forEach(function(node) {
                if (node.nodeType === 3) { el.removeChild(node); } /* TEXT_NODE */
            });
            /* BUG-CI-ICONBREAK-JS: Force FontAwesome via inline !important  -  beats everything */
            el.style.setProperty('font-family', 'FontAwesome, "Font Awesome 6 Free", "Font Awesome 5 Free"', 'important');
            el.style.setProperty('font-weight', '900', 'important');
            el.style.setProperty('font-style', 'normal', 'important');
            el.style.setProperty('display', 'inline-block', 'important');
            el.style.setProperty('speak', 'never', 'important');
            el.style.setProperty('-webkit-font-smoothing', 'antialiased', 'important');
            el.style.setProperty('text-rendering', 'auto', 'important');
        });
    }

    function formatMinutes(mins) {
        if (!mins) { return ' - '; }
        if (mins < 60) { return mins + ' min'; }
        var h = Math.floor(mins / 60), m = mins % 60;
        return m > 0 ? h + 'h ' + m + 'm' : h + 'h';
    }

    /* -- AJAX helper ------------------------------------------------------- */
    function ajaxCall(action, callback, extraParams) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', M.cfg.wwwroot + '/mod/courseinfo/ajax.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try { callback(null, JSON.parse(xhr.responseText)); }
                    catch (e) { callback('Invalid response from server'); }
                } else { callback('Server error: ' + xhr.status); }
            }
        };
        var body = 'action=' + encodeURIComponent(action) + '&cmid=' + cmid + '&sesskey=' + M.cfg.sesskey;
        if (extraParams) { body += '&' + extraParams; }
        xhr.send(body);
    }

    /* -- Credit cost confirmation popup ----------------------------------- */
    function ciShowConfirm(message, opts) {
        opts = opts || {};
        var title       = opts.title       || 'Confirm';
        var confirmText = opts.confirmText || 'Confirm';
        var cancelText  = opts.cancelText  || 'Cancel';
        var type        = opts.type        || 'default';

        var accentColor = type === 'danger' ? '#dc3545' : type === 'warning' ? '#f59e0b' : themePrimary;

        return new Promise(function(resolve) {
            var overlay = document.createElement('div');
            overlay.style.cssText = [
                'position:fixed', 'top:0', 'left:0', 'right:0', 'bottom:0',
                'background:rgba(0,0,0,0.5)', 'z-index:99999',
                'display:flex', 'align-items:center', 'justify-content:center',
                'opacity:0', 'transition:opacity 0.18s ease'
            ].join(';');

            var card = document.createElement('div');
            card.style.cssText = [
                'background:#fff', 'border-radius:8px', 'max-width:460px', 'width:90%',
                'box-shadow:0 8px 32px rgba(0,0,0,0.22)', 'overflow:hidden',
                'transform:translateY(-12px)', 'transition:transform 0.18s ease'
            ].join(';');

            var header = document.createElement('div');
            header.style.cssText = 'display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid #e5e7eb;background:#f9fafb;';
            var titleEl = document.createElement('span');
            titleEl.style.cssText = 'font-weight:700;font-size:14px;color:#111827;';
            titleEl.textContent = title;
            var closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.innerHTML = '&times;';
            closeBtn.style.cssText = 'background:none;border:none;font-size:20px;line-height:1;color:#6b7280;cursor:pointer;padding:0 4px;';
            header.appendChild(titleEl);
            header.appendChild(closeBtn);

            var body = document.createElement('div');
            body.style.cssText = 'padding:18px 20px;font-size:13px;color:#374151;line-height:1.6;';
            body.innerHTML = message.replace(/\n/g, '<br>');

            var footer = document.createElement('div');
            footer.style.cssText = 'display:flex;justify-content:flex-end;gap:10px;padding:12px 18px;border-top:1px solid #e5e7eb;background:#f9fafb;';

            var cancelBtn = null;
            if (cancelText) {
                cancelBtn = document.createElement('button');
                cancelBtn.type = 'button';
                cancelBtn.textContent = cancelText;
                cancelBtn.style.cssText = 'padding:7px 16px;border:1px solid #d1d5db;border-radius:5px;background:#fff;color:#374151;font-size:13px;cursor:pointer;font-weight:500;';
                cancelBtn.addEventListener('mouseover', function() { this.style.background = '#f3f4f6'; });
                cancelBtn.addEventListener('mouseout',  function() { this.style.background = '#fff'; });
                footer.appendChild(cancelBtn);
            }

            var okBtn = document.createElement('button');
            okBtn.type = 'button';
            okBtn.textContent = confirmText;
            okBtn.style.cssText = 'padding:7px 16px;border:1px solid ' + accentColor + ';border-radius:5px;background:' + accentColor + ';color:#fff;font-size:13px;cursor:pointer;font-weight:600;';
            okBtn.addEventListener('mouseover', function() { this.style.opacity = '0.88'; });
            okBtn.addEventListener('mouseout',  function() { this.style.opacity = '1'; });
            footer.appendChild(okBtn);

            card.appendChild(header);
            card.appendChild(body);
            card.appendChild(footer);
            overlay.appendChild(card);
            document.body.appendChild(overlay);

            setTimeout(function() {
                overlay.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 10);

            function close(result) {
                overlay.style.opacity = '0';
                card.style.transform = 'translateY(-12px)';
                setTimeout(function() {
                    if (overlay.parentNode) { overlay.parentNode.removeChild(overlay); }
                }, 200);
                resolve(result);
            }

            closeBtn.addEventListener('click', function() { close(false); });
            if (cancelBtn) { cancelBtn.addEventListener('click', function() { close(false); }); }
            okBtn.addEventListener('click', function() { close(true); });
            overlay.addEventListener('click', function(e) { if (e.target === overlay) { close(false); } });
            overlay.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') { close(false); }
                if (e.key === 'Enter')  { close(true); }
            });
            overlay.setAttribute('tabindex', '-1');
            overlay.focus();
        });
    }

    /* -- Credit check + generate ------------------------------------------- */
    function checkCreditsAndGenerate() {
        var btnGen   = document.getElementById('btn-generate');
        var origHtml = btnGen ? btnGen.innerHTML : '';
        var COST     = 100;

        if (btnGen) {
            btnGen.disabled = true;
            btnGen.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> Checking credits\u2026';
        }

        ajaxCall('getcredits', function(err, data) {
            if (btnGen) { btnGen.disabled = false; btnGen.innerHTML = origHtml; }

            if (err || !data || !data.success) {
                /* Cannot reach credits API  -  warn and offer to proceed anyway */
                ciShowConfirm(
                    'Unable to check your credit balance.\n\n' +
                    'Generating Course Information costs <strong>' + COST + ' credits ($10.00 AUD)</strong>. ' +
                    'Your account will be debited if you have sufficient balance.\n\n' +
                    'Proceed anyway?',
                    { title: 'Generate Course Information', confirmText: 'Generate', cancelText: 'Cancel', type: 'warning' }
                ).then(function(confirmed) {
                    if (confirmed) { generateCourseInfo(); }
                });
                return;
            }

            var balance = data.credits;

            if (balance !== -1 && balance < COST) {
                showError(
                    'Insufficient credits. Generating Course Information costs <strong>' + COST + ' credits ($10.00 AUD)</strong>. ' +
                    'Your current balance is <strong>' + balance + ' credits</strong>. ' +
                    'Please top up your account to continue.'
                );
                return;
            }

            var balanceHtml = (balance === -1)
                ? '<strong>Unlimited</strong>'
                : '<strong>' + balance + ' credits</strong>';
            var afterHtml = (balance === -1)
                ? '<strong>Unlimited</strong>'
                : '<strong>' + (balance - COST) + ' credits</strong>';

            ciShowConfirm(
                'Generating Course Information costs <strong>' + COST + ' credits ($10.00 AUD)</strong>.\n\n' +
                'Current balance: ' + balanceHtml + '\n' +
                'After generation: ' + afterHtml,
                { title: 'Generate Course Information', confirmText: 'Generate', cancelText: 'Cancel', type: 'default' }
            ).then(function(confirmed) {
                if (confirmed) { generateCourseInfo(); }
            });
        });
    }

    /* -- Save edits -------------------------------------------------------- */
    function saveEdits() {
        var contentEl = document.getElementById('courseinfo-generated');
        if (!contentEl) { return; }
        showStatus('Saving...');
        var xhr = new XMLHttpRequest();
        xhr.open('POST', M.cfg.wwwroot + '/mod/courseinfo/ajax.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try {
                        var data = JSON.parse(xhr.responseText);
                        if (data.success) { hideStatus(); toggleEdit(false); }
                        else { showError(data.error || 'Save failed'); }
                    } catch(e) { showError('Invalid response'); }
                } else { showError('Server error: ' + xhr.status); }
            }
        };
        xhr.send('action=save&cmid=' + cmid + '&sesskey=' + M.cfg.sesskey + '&html=' + encodeURIComponent(contentEl.innerHTML));
    }

    /* -- Scan activities --------------------------------------------------- */
    function scanActivities() {
        showStatus('Scanning all course activities...');
        var scanResults = document.getElementById('courseinfo-scan-results');
        ajaxCall('scan', function(err, data) {
            if (err) { showError(err); return; }
            if (!data.success) { showError(data.error || 'Scan failed'); return; }
            hideStatus();
            if (scanResults) { scanResults.innerHTML = renderScanResults(data.data); scanResults.style.display = 'block'; }
        });
    }

    function renderScanResults(scanData) {
        var border  = tintHex(themePrimary, 0.18);
        var surface = tintHex(themePrimary, 0.06);
        var iconBg  = tintHex(themePrimary, 0.12);
        var html = '<div class="ci-card" style="border:1px solid ' + border + ';border-radius:12px;padding:28px 32px;margin-bottom:24px;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,0.07);">';
        html += '<div style="display:flex;align-items:center;gap:14px;margin-bottom:18px;">';
        html += '<div style="width:44px;height:44px;border-radius:50%;background:' + iconBg + ';display:flex;align-items:center;justify-content:center;flex-shrink:0;">';
        html += '<i class="fa fa-search" style="color:' + themeSecondary + ';font-size:18px;"></i></div>';
        html += '<h3 style="margin:0;font-size:1.1em;font-weight:700;color:#1e293b;">Activity Scan Results</h3></div>';
        html += '<p style="margin:0 0 14px;color:#475569;"><strong style="color:#1e293b;">' + (scanData.summary ? scanData.summary.total_activities : 0) + '</strong> activities found in this course.</p>';

        if (scanData.environment) {
            var env = scanData.environment, envBadges = '';
            /* BUG-CI-BOOTSTRAP-MB: was class="mb-2" (Bootstrap dep). Fixed: inline style only. */
            /* BUG-CI-BOOTSTRAP-MB: was class="badge" (Bootstrap dep). Fixed: full inline styles. */
            var badgeStyle = 'display:inline-block;font-size:0.78em;font-weight:600;padding:2px 8px;border-radius:4px;margin-right:4px;color:#fff;';
            if (env.ai_course_format)    { envBadges += '<span style="' + badgeStyle + 'background:' + themePrimary   + ';">AI Course Format</span>'; }
            if (env.ai_tutor_enabled)    { envBadges += '<span style="' + badgeStyle + 'background:' + themeSecondary + ';">AI Tutor</span>'; }
            if (env.ai_support_installed){ envBadges += '<span style="' + badgeStyle + 'background:#ea4335;">AI Support</span>'; }
            if (envBadges) { html += '<div style="margin-bottom:12px;">' + envBadges + '</div>'; }
        }

        if (scanData.activities && scanData.activities.length > 0) {
            /* BUG-CI-SCAN-NOSWATCH + BUG-CI-SCAN-RAWTYPE: type colour swatches and
               friendly display labels were absent after the Bootstrap-removal rewrite.
               The type column showed raw modnames ("contentcreator") and the Activity
               column had no visual type indicator. Fixed: added colour swatch (same
               10 x 10px pattern as the volume breakdown table) and a friendlyTypes map. */
            var typeColors = {
                contentcreator:'', aiactivities:'', essaymaker:'#ea4335',
                knowledgecheck:'#fbbc04', assignment:'#673ab7', practicalassessment:'#ff6d00',
                quiz:'#607d8b', forum:'#607d8b', resource:'#607d8b', videoactivity:'#2196f3', other:'#607d8b'
            };
            typeColors.contentcreator  = themePrimary;
            typeColors.aiactivities    = themeSecondary;
            var typeLabels = {
                contentcreator:'AI Content Creator', aiactivities:'AI Learning Activities',
                essaymaker:'AI Essay Grader', knowledgecheck:'AI Knowledge Check',
                assignment:'Assignment', practicalassessment:'Practical Assessment',
                quiz:'Quiz', forum:'Forum', resource:'Resource',
                videoactivity:'Video Activity', other:'Other'
            };

            html += '<table style="width:100%;border-collapse:collapse;font-size:0.85em;">';
            html += '<thead><tr style="background:' + surface + ';border-bottom:1px solid ' + border + ';">';
            html += '<th style="padding:6px 10px;font-weight:600;color:#475569;white-space:nowrap;">#</th>';
            html += '<th style="padding:6px 10px;font-weight:600;color:#475569;">Activity</th>';
            html += '<th style="padding:6px 10px;font-weight:600;color:#475569;white-space:nowrap;">Type</th>';
            html += '<th style="padding:6px 10px;font-weight:600;color:#475569;">Section</th>';
            html += '<th style="padding:6px 10px;font-weight:600;color:#475569;text-align:right;white-space:nowrap;">Est. Time</th>';
            html += '</tr></thead><tbody>';
            for (var i = 0; i < scanData.activities.length; i++) {
                var act = scanData.activities[i];
                var rowBg = i % 2 === 1 ? surface : '#fff';
                var actType = act.type || act.modname || 'other';
                var swatchColor = typeColors[actType] || '#607d8b';
                var friendlyType = typeLabels[actType] || actType;
                html += '<tr style="background:' + rowBg + ';border-bottom:1px solid ' + border + ';">';
                html += '<td style="padding:5px 10px;color:#64748b;white-space:nowrap;">' + (i+1) + '</td>';
                html += '<td style="padding:5px 10px;color:#1e293b;">';
                html +=   '<span style="display:inline-block;width:10px;height:10px;background:' + swatchColor + ';border-radius:2px;margin-right:8px;vertical-align:middle;flex-shrink:0;"></span>';
                html +=   escapeHtml(act.name||'Untitled');
                html += '</td>';
                html += '<td style="padding:5px 10px;white-space:nowrap;"><span style="background:#e8eaed;color:#333;font-size:0.78em;padding:2px 7px;border-radius:4px;">' + escapeHtml(friendlyType) + '</span></td>';
                html += '<td style="padding:5px 10px;color:#475569;">' + escapeHtml(act.section_name||'') + '</td>';
                html += '<td style="padding:5px 10px;text-align:right;color:#64748b;white-space:nowrap;">' + formatMinutes(act.estimated_minutes||0) + '</td>';
                html += '</tr>';
            }
            html += '</tbody></table>';
        }

        /* BUG-CI-VOL-NESTED: volume panel was rendered BEFORE html += '</div>', meaning it
           was a CHILD of the scan results card div  -  a rounded ci-card nested inside another
           rounded ci-card, causing layout collapse (second red-arrow symptom).
           Fixed: close the scan results card div first, THEN append the volume panel
           as a sibling element (both inside #courseinfo-scan-results innerHTML). */
        html += '</div>'; /* close scan results card */
        if (enableVol && scanData.volume) { html += renderVolumePanel(scanData.volume); }
        return html;
    }

    function renderVolumePanel(vol) {
        /* Identical structure to renderVolumeCard  -  same card, heading, icon, stats, table */
        return renderVolumeCard(vol);
    }

    function renderVolumeCard(vol) {
        /* BUG-CI-VOLCSS: Old design used Bootstrap "card" + border-left:4px on a rounded
           element. Rewritten to match the section-card design: full border, border-radius,
           icon circle + heading  -  identical pattern to AI-generated sections. */
        var border  = tintHex(themePrimary, 0.18);
        var surface = tintHex(themePrimary, 0.06);
        var colors = { contentcreator:themePrimary, aiactivities:themeSecondary, essaymaker:'#ea4335', knowledgecheck:'#fbbc04', assignment:'#673ab7', practicalassessment:'#ff6d00', other:'#607d8b' };
        var totalHours = vol.total_hours || 0, nominalHours = vol.nominal_hours || 0, compliant = !!vol.compliant;
        var gapHours = Math.max(0, nominalHours - totalHours);
        var sCol = compliant ? themeSecondary : '#dc3545';

        /* Card wrapper  -  matches section card design */
        var html = '<div class="ci-card" style="background:#fff;border:1px solid ' + border + ';border-radius:12px;padding:28px 32px;margin-bottom:24px;box-shadow:0 2px 8px rgba(0,0,0,0.07);">';

        /* Icon circle + heading row */
        html += '<div style="display:flex;align-items:center;gap:14px;margin-bottom:20px;">';
        html += '<div class="ci-icon" style="width:44px;height:44px;border-radius:50%;background:' + surface + ';display:flex;align-items:center;justify-content:center;flex-shrink:0;">';
        html += '<i class="fa fa-clock-o" style="font-size:20px;color:' + themePrimary + ';"></i></div>';
        html += '<h3 style="margin:0;font-size:1.1em;font-weight:700;color:#1e293b;">Volume of Learning Summary</h3>';
        html += '</div>';

        /* Stats row  -  three tiles using flexbox (no Bootstrap grid) */
        html += '<div style="display:flex;gap:12px;margin-bottom:20px;">';
        html += '<div style="flex:1;padding:14px 12px;background:' + surface + ';border-radius:8px;text-align:center;">';
        html += '<div style="font-size:1.9em;font-weight:700;color:' + themePrimary + ';">' + totalHours + '</div>';
        html += '<div style="font-size:0.82em;color:#64748b;margin-top:4px;">Total Hours</div></div>';

        html += '<div style="flex:1;padding:14px 12px;background:' + surface + ';border-radius:8px;text-align:center;">';
        html += '<div style="font-size:1.9em;font-weight:700;color:#1e293b;">' + nominalHours + '</div>';
        html += '<div style="font-size:0.82em;color:#64748b;margin-top:4px;">Nominal Required</div></div>';

        html += '<div style="flex:1;padding:14px 12px;background:' + surface + ';border-radius:8px;text-align:center;">';
        html += '<div style="font-size:1.9em;font-weight:700;color:' + sCol + ';"><i class="fa ' + (compliant ? 'fa-check-circle' : 'fa-exclamation-triangle') + '"></i></div>';
        html += '<div style="font-size:0.82em;color:' + sCol + ';font-weight:600;margin-top:4px;">' + (compliant ? 'Compliant' : 'Non-Compliant') + '</div></div>';
        html += '</div>';

        /* Gap alert when non-compliant */
        if (!compliant && gapHours > 0) {
            html += '<div style="background:#fff8e6;border:1px solid #f0c040;border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:0.88em;color:#7a5a00;">';
            html += '<i class="fa fa-exclamation-triangle" style="margin-right:6px;"></i>';
            html += 'Volume gap: <strong>' + gapHours + ' hours</strong> below the ' + nominalHours + ' hr requirement.</div>';
        }

        var catOrder = ['contentcreator','aiactivities','essaymaker','knowledgecheck','assignment','practicalassessment','other'];
        html += '<table style="width:100%;border-collapse:collapse;font-size:0.88em;">';
        html += '<thead><tr style="border-bottom:2px solid ' + border + ';">';
        html += '<th style="padding:8px 4px;text-align:left;font-weight:600;color:#475569;">Category</th>';
        html += '<th style="padding:8px 4px;text-align:center;font-weight:600;color:#475569;">Activities</th>';
        html += '<th style="padding:8px 4px;text-align:right;font-weight:600;color:#475569;">Hours</th>';
        html += '</tr></thead><tbody>';

        if (vol.sections_breakdown && vol.sections_breakdown.length > 0) {
            /* -- Section-grouped rows (v1.0.19+) -- */
            var sects = vol.sections_breakdown;
            for (var si = 0; si < sects.length; si++) {
                var sect = sects[si], sectCats = sect.categories || {};
                /* Skip sections that have no activities at all */
                var hasContent = false;
                for (var ck = 0; ck < catOrder.length; ck++) {
                    var sc = sectCats[catOrder[ck]];
                    if (sc && sc.count > 0) { hasContent = true; break; }
                }
                if (!hasContent) { continue; }
                /* Section header row */
                html += '<tr style="background:' + surface + ';">';
                html += '<td colspan="3" style="font-weight:600;font-size:0.85em;padding:6px 4px;color:' + themePrimary + ';">';
                html += '<i class="fa fa-folder-open-o" style="margin-right:6px;"></i>' + escapeHtml(sect.name) + '</td></tr>';
                /* Category rows for this section, in canonical order */
                for (var ci2 = 0; ci2 < catOrder.length; ci2++) {
                    var catKey = catOrder[ci2], catData = sectCats[catKey];
                    if (!catData || catData.count <= 0) { continue; }
                    html += '<tr style="border-bottom:1px solid ' + border + ';">';
                    html += '<td style="padding:7px 4px 7px 22px;"><span style="display:inline-block;width:10px;height:10px;background:' + (colors[catKey]||'#607d8b') + ';border-radius:2px;margin-right:8px;"></span>' + escapeHtml(catData.label) + '</td>';
                    html += '<td style="padding:7px 4px;text-align:center;">' + catData.count + '</td>';
                    html += '<td style="padding:7px 4px;text-align:right;font-weight:600;">' + catData.hours + ' hrs</td>';
                    html += '</tr>';
                }
            }
        } else if (vol.breakdown) {
            /* -- Flat category rows (backward compat for pre-v1.0.19 scan data) -- */
            for (var ci = 0; ci < catOrder.length; ci++) {
                var cat = catOrder[ci], d = vol.breakdown[cat];
                if (!d || (d.minutes <= 0 && d.count <= 0)) { continue; }
                html += '<tr style="border-bottom:1px solid ' + border + ';">';
                html += '<td style="padding:7px 4px;"><span style="display:inline-block;width:10px;height:10px;background:' + (colors[cat]||'#607d8b') + ';border-radius:2px;margin-right:8px;"></span>' + escapeHtml(d.label) + '</td>';
                html += '<td style="padding:7px 4px;text-align:center;">' + d.count + '</td>';
                html += '<td style="padding:7px 4px;text-align:right;font-weight:600;">' + d.hours + ' hrs</td>';
                html += '</tr>';
            }
        }

        html += '<tr style="border-top:2px solid ' + border + ';font-weight:700;">';
        html += '<td style="padding:8px 4px;">Total</td><td></td>';
        html += '<td style="padding:8px 4px;text-align:right;">' + totalHours + ' hrs</td></tr>';
        html += '</tbody></table>';
        html += '</div>'; /* close ci-card */
        return html;
    }

    /* -- Generate ---------------------------------------------------------- */
    function generateCourseInfo() {
        showStatus('Generating comprehensive course information\u2026 This may take up to 90 seconds.');
        var btnGen = document.getElementById('btn-generate');
        if (btnGen) { btnGen.disabled = true; }

        /* BUG-CI-NOTICE-PERSISTS: The "Course information has not been generated yet"
           warning inside #courseinfo-generated remains visible throughout the ~90-second
           generation wait alongside the generating spinner, which is confusing.
           Hide the generated div immediately so only the spinner status is shown. */
        var noticeDivGen = document.getElementById('courseinfo-generated');
        if (noticeDivGen) {
            noticeDivGen.style.transition = 'opacity 0.3s ease';
            noticeDivGen.style.opacity = '0';
        }

        var extra = 'primaryColour=' + encodeURIComponent(themePrimary) + '&secondaryColour=' + encodeURIComponent(themeSecondary);

        /* v1.0.33 ASYNC: apply the finished generation result, extracted so both the
           sync fallback path and the async poll path can share the same handler. */
        function applyResult(data) {
            if (btnGen) { btnGen.disabled = false; }
            if (!data.success) {
                /* BUG-CI-NOTICE-PERSISTS: Restore generated div visibility on failure so
                   the "not generated yet" warning becomes readable again. */
                var genElFail = document.getElementById('courseinfo-generated');
                if (genElFail) { genElFail.style.opacity = '1'; }
                showError(data.error || 'Generation failed');
                return;
            }
            hideStatus();

            var contentEl = document.getElementById('courseinfo-generated');
            if (contentEl && data.html) {
                contentEl.style.opacity = '0';
                contentEl.style.transition = 'opacity 0.5s ease';
                contentEl.innerHTML = sanitizeGeneratedHtml(data.html);
                fixFaIconsInDom(contentEl);
                /* BUG-CI-BLANK-CONTENT: Force-reveal any ci-reveal elements immediately.
                   The AI generates <script> IntersectionObserver scroll-reveal code that
                   sets opacity:0 on .ci-reveal cards. sanitizeGeneratedHtml() strips those
                   scripts (browsers don't execute innerHTML scripts anyway), but the CSS
                   animation rule that initialises opacity:0 survives in the <style> block.
                   Force all ci-reveal elements to be fully visible right after injection. */
                contentEl.querySelectorAll('.ci-reveal').forEach(function(el) {
                    el.style.setProperty('opacity', '1', 'important');
                    el.style.setProperty('animation', 'none', 'important');
                    el.style.setProperty('transform', 'none', 'important');
                    el.style.setProperty('visibility', 'visible', 'important');
                });
                if (heroColour) { applyHeroColour(heroColour); }
                addHeroEditOverlay();
                requestAnimationFrame(function() {
                    requestAnimationFrame(function() {
                        contentEl.style.opacity = '1';
                        contentEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    });
                });
            }
            var msgEl = document.getElementById('courseinfo-content');
            if (msgEl) { msgEl.innerHTML = ''; }

            ['btn-print','btn-edit','btn-add-box'].forEach(function(id) {
                var el = document.getElementById(id);
                if (el) { el.style.display = ''; }
            });
            var heroWrap = document.getElementById('btn-hero-color-wrap');
            if (heroWrap) { heroWrap.style.display = 'inline-flex'; }

            var statusEl = document.getElementById('courseinfo-status');
            if (statusEl) {
                statusEl.className = 'alert alert-success mb-3';
                statusEl.style.display = '';
                var complianceBadge = '';
                if (data.compliance) {
                    var c = data.compliance;
                    if (c.compliant) {
                        complianceBadge = ' <span style="display:inline-block;background:#16a34a;color:#fff;font-size:11px;font-weight:600;padding:2px 10px;border-radius:20px;margin-left:8px;vertical-align:middle;">'
                            + '<i class="fa fa-shield mr-1"></i>Audit Compliant &mdash; ' + c.score + '%</span>';
                    } else {
                        var missingCount = (c.missing || []).length;
                        complianceBadge = ' <span style="display:inline-block;background:#dc2626;color:#fff;font-size:11px;font-weight:600;padding:2px 10px;border-radius:20px;margin-left:8px;vertical-align:middle;" title="' + (c.missing || []).join('; ') + '">'
                            + '<i class="fa fa-exclamation-triangle mr-1"></i>Not Compliant &mdash; ' + missingCount + ' field' + (missingCount !== 1 ? 's' : '') + ' missing</span>';
                    }
                }
                statusEl.innerHTML = '<i class="fa fa-check-circle mr-2"></i> Course information generated successfully. Use <strong>Edit</strong> to customise or <strong>Add Box</strong> to insert custom sections.' + complianceBadge;
            }

            if (data.volume && enableVol) {
                var volEl = document.getElementById('courseinfo-volume');
                if (!volEl) {
                    volEl = document.createElement('div');
                    volEl.id = 'courseinfo-volume';
                    volEl.className = 'mt-4';
                    var appEl = document.getElementById('courseinfo-app');
                    if (appEl) { appEl.appendChild(volEl); }
                }
                if (volEl) { volEl.innerHTML = renderVolumeCard(data.volume); }
            }

            if (btnGen) { btnGen.innerHTML = '<i class="fa fa-magic mr-1"></i> Regenerate Course Information'; }
            applyThemeCssVars();
        }

        /* Poll the background job every 3s until status=done|error. */
        function doPoll(jobId) {
            ajaxCall('poll', function(err, pollData) {
                if (err) { showError(err); if (btnGen) { btnGen.disabled = false; } return; }
                if (!pollData || !pollData.ok) {
                    showError((pollData && pollData.error) || 'Failed to check generation status');
                    if (btnGen) { btnGen.disabled = false; }
                    return;
                }
                if (pollData.status === 'done')  { applyResult(pollData.result); return; }
                if (pollData.status === 'error')  {
                    showError(pollData.error || 'Generation failed');
                    if (btnGen) { btnGen.disabled = false; }
                    return;
                }
                /* pending / processing  -  keep polling */
                setTimeout(function() { doPoll(jobId); }, 3000);
            }, 'jobId=' + encodeURIComponent(jobId));
        }

        /* v1.0.33 ASYNC: Start job immediately (returns jobId in ~500ms), then poll. */
        ajaxCall('generate_async', function(err, startData) {
            if (err) { showError(err); if (btnGen) { btnGen.disabled = false; } return; }
            if (!startData || !startData.success || !startData.jobId) {
                showError((startData && startData.error) || 'Failed to start generation');
                if (btnGen) { btnGen.disabled = false; }
                return;
            }
            /* First poll after 3s to give the loopback call time to reach OpenAI. */
            setTimeout(function() { doPoll(startData.jobId); }, 3000);
        }, extra);
    }

    /* -- Print ------------------------------------------------------------- */
    function printCourseInfo() {
        var gen   = document.getElementById('courseinfo-generated');
        var boxes = document.getElementById('courseinfo-custom-boxes');
        if (!gen) { return; }
        var pw = window.open('', '_blank');
        pw.document.write('<!DOCTYPE html><html><head><title>Course Information</title>');
        pw.document.write('<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">');
        pw.document.write('<style>body{font-family:system-ui,sans-serif;font-size:13px;color:#333;margin:20px;}.card-header{padding:14px 18px;background:#f5f7fa;border-bottom:1px solid #e0e0e0;}.ci-box-controls{display:none!important;}@media print{@page{margin:15mm;}body{margin:0;}}</style>');
        pw.document.write('</head><body>');
        pw.document.write(gen.innerHTML);
        if (boxes && boxes.innerHTML.trim()) { pw.document.write(boxes.innerHTML); }
        pw.document.write('</body></html>');
        pw.document.close();
        setTimeout(function() { pw.print(); }, 600);
    }

    return { init: init };
});
