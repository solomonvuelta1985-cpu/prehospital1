/* Mobile action menu layer.
 *
 * The source Bootstrap menu stays in its table cell so Bootstrap's dropdown
 * lifecycle remains intact. On phones, this creates a temporary visual copy
 * in a viewport-level layer and forwards the selected action to the original
 * link. This prevents scroll-container clipping without reparenting the
 * Bootstrap-owned menu.
 */
(function () {
    'use strict';

    var MOBILE_MAX = 768;
    var active = null;

    function isMobile() {
        return window.innerWidth <= MOBILE_MAX;
    }

    function closeMenu() {
        if (!active) return;
        if (active.layer && active.layer.parentNode) {
            active.layer.parentNode.removeChild(active.layer);
        }
        active = null;
    }

    function openMenu(button) {
        var dropdown = button.closest('.action-dropdown');
        var sourceMenu = dropdown ? dropdown.querySelector('.dropdown-menu') : null;
        if (!sourceMenu) return;

        closeMenu();

        // Ensure the Bootstrap-owned source menu is never visible together
        // with the viewport copy.
        sourceMenu.classList.remove('show', 'action-menu-floating');
        ['position', 'top', 'left', 'right', 'bottom', 'z-index'].forEach(function (property) {
            sourceMenu.style.removeProperty(property);
        });

        var sourceLinks = Array.prototype.slice.call(sourceMenu.querySelectorAll('a.dropdown-item'));
        var layer = document.createElement('div');
        layer.className = 'mobile-action-menu-layer is-open';
        layer.setAttribute('aria-label', 'Actions menu');

        var shell = document.createElement('div');
        shell.className = 'dropdown action-dropdown mobile-action-menu-shell';

        var menu = sourceMenu.cloneNode(true);
        menu.classList.remove('show');
        menu.classList.add('show', 'mobile-action-menu');
        menu.removeAttribute('id');
        shell.appendChild(menu);
        layer.appendChild(shell);
        document.body.appendChild(layer);

        active = { layer: layer, menu: menu, sourceLinks: sourceLinks };

        var rect = button.getBoundingClientRect();
        var maxWidth = Math.max(1, window.innerWidth - 16);
        var width = Math.min(Math.max(menu.offsetWidth || 192, 192), maxWidth);
        menu.style.setProperty('width', width + 'px', 'important');

        var menuHeight = menu.offsetHeight || 0;
        var left = Math.max(8, Math.min(rect.right - width, window.innerWidth - width - 8));
        var top = rect.bottom + 4;
        if (menuHeight && top + menuHeight > window.innerHeight - 8) {
            top = Math.max(8, rect.top - menuHeight - 4);
        }

        menu.style.setProperty('left', left + 'px', 'important');
        menu.style.setProperty('top', top + 'px', 'important');
        menu.style.setProperty('right', 'auto', 'important');
        menu.style.setProperty('bottom', 'auto', 'important');

        layer.addEventListener('click', function (event) {
            var item = event.target.closest ? event.target.closest('a.dropdown-item') : null;
            if (!item) {
                closeMenu();
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            var index = Array.prototype.indexOf.call(menu.querySelectorAll('a.dropdown-item'), item);
            var sourceLink = index >= 0 ? sourceLinks[index] : null;
            closeMenu();
            if (sourceLink) sourceLink.click();
        });
    }

    // Capture before Bootstrap's document click handler on mobile. Desktop
    // continues using the normal Bootstrap dropdown without modification.
    document.addEventListener('click', function (event) {
        if (!isMobile()) return;
        var button = event.target.closest ? event.target.closest('.action-dropdown [data-bs-toggle="dropdown"]') : null;
        if (!button) return;

        event.preventDefault();
        event.stopImmediatePropagation();
        if (active) {
            closeMenu();
        } else {
            openMenu(button);
        }
    }, true);

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeMenu();
    });

    window.addEventListener('resize', closeMenu);
    window.addEventListener('scroll', closeMenu, true);
})();
