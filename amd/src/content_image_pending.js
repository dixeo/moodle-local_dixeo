// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <https://www.gnu.org/licenses/>.

/**
 * Display-time host + status labels for pending/failed content images.
 *
 * Stores bare imgs; wraps a shimmer host and injects translated status pills.
 * On course pages, polls until generation completes and swaps the live image.
 * Does not page-poll imgs inside .dixeo-imageeditor-wrap (filter owns that path).
 * Safe to run inside the Dixeo editor TinyMCE iframe (uses ownerDocument).
 *
 * @module     local_dixeo/content_image_pending
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/str', 'core/ajax'], function(Str, Ajax) {
    'use strict';

    const IMG_SELECTOR = 'img.dixeo-img-gen-pending, img.dixeo-img-gen-failed';
    const FRAME_CLASS = 'dixeo-img-gen-frame';
    const STATUS_CLASS = 'dixeo-img-gen-status';
    const WRAP_CLASS = 'dixeo-imageeditor-wrap';
    const GENERATING_CLASS = 'is-generating';
    const POLL_INTERVAL_MS = 3000;
    /** Cap client polling (~10 min) for stuck pending HTML without a live job. */
    const MAX_POLL_MS = 10 * 60 * 1000;

    /** @type {{generating: string, failed: string}|null} */
    let labels = null;

    /** @type {Promise<{generating: string, failed: string}>|null} */
    let labelsPromise = null;

    /** @type {WeakSet<Document>} */
    const observedDocs = new WeakSet();

    /** @type {number|null} */
    let pagePollTimer = null;

    /** @type {boolean} */
    let pagePollInFlight = false;

    /** @type {number} */
    let pagePollStartedAt = 0;

    /**
     * @returns {Promise<{generating: string, failed: string}>}
     */
    const loadLabels = () => {
        if (labels) {
            return Promise.resolve(labels);
        }
        if (!labelsPromise) {
            labelsPromise = Str.get_strings([
                {key: 'content_image_generating', component: 'local_dixeo'},
                {key: 'content_image_failed', component: 'local_dixeo'},
            ]).then((strings) => {
                labels = {
                    generating: strings[0],
                    failed: strings[1],
                };
                return labels;
            });
        }
        return labelsPromise;
    };

    /**
     * Append/replace a cache-busting rev query param (contenthash when available).
     *
     * @param {string} url
     * @param {string} rev
     * @returns {string}
     */
    const appendImageRev = (url, rev) => {
        if (!url || !rev) {
            return url;
        }
        const cleaned = url.replace(/([?&])rev=[^&]*/g, '$1').replace(/[?&]$/, '');
        const separator = cleaned.includes('?') ? '&' : '?';
        return cleaned + separator + 'rev=' + encodeURIComponent(rev);
    };

    /**
     * Force the browser to refetch after in-place file replacement.
     *
     * @param {HTMLImageElement} img
     */
    const bustImageCache = (img) => {
        const wrap = img.closest(`.${WRAP_CLASS}`);
        // Prefer the live file hash on the filter wrap over a stale error hash in HTML.
        const rev = (wrap && wrap.dataset.contenthash) ||
            img.dataset.dixeoContenthash ||
            (img.classList.contains('dixeo-img-gen-failed') ? 'failed' : '');
        if (!rev || !img.src) {
            return;
        }
        const next = appendImageRev(img.src, rev);
        if (next !== img.src) {
            img.src = next;
        }
    };

    /**
     * True for <img> nodes, including those from another document (TinyMCE iframe).
     * Cross-frame `instanceof HTMLImageElement` is false against the parent realm.
     *
     * @param {Node|null|undefined} node
     * @returns {boolean}
     */
    const isHtmlImage = (node) => {
        return !!node && node.nodeType === 1 && node.tagName === 'IMG';
    };

    /**
     * @param {HTMLImageElement} img
     * @returns {'pending'|'failed'|null}
     */
    const imageState = (img) => {
        if (img.classList.contains('dixeo-img-gen-pending')) {
            return 'pending';
        }
        if (img.classList.contains('dixeo-img-gen-failed')) {
            return 'failed';
        }
        return null;
    };

    /**
     * Resolve placeholder UUID from data attribute or dixeo-gen stub filename.
     *
     * @param {HTMLImageElement} img
     * @returns {string}
     */
    const placeholderIdFromImg = (img) => {
        const fromData = (img.getAttribute('data-dixeo-img-gen') || '').trim();
        if (fromData) {
            return fromData;
        }
        const src = img.getAttribute('src') || '';
        const match = src.match(/dixeo-gen-([a-f0-9-]+)\.png/i);
        return match ? match[1] : '';
    };

    /**
     * Filter wrap owns location polling / labels for these hosts.
     *
     * @param {HTMLImageElement} img
     * @returns {boolean}
     */
    const isInsideEditorWrap = (img) => {
        return !!img.closest(`.${WRAP_CLASS}`);
    };

    /**
     * @param {ParentNode|Document|null|undefined} root
     * @returns {{searchRoot: ParentNode|Document, observeRoot: Node|null, doc: Document}}
     */
    const resolveRoots = (root) => {
        const scope = root || document;
        const doc = scope.nodeType === Node.DOCUMENT_NODE
            ? /** @type {Document} */ (scope)
            : (scope.ownerDocument || document);
        const searchRoot = scope;
        const observeRoot = scope.nodeType === Node.DOCUMENT_NODE
            ? doc.body
            : scope;
        return {searchRoot, observeRoot, doc};
    };

    /**
     * @param {Element} host
     * @param {'pending'|'failed'} state
     * @param {{generating: string, failed: string}} strings
     */
    const ensureStatus = (host, state, strings) => {
        if (host.classList.contains(GENERATING_CLASS)) {
            // Filter owns the generating label on .is-generating wraps.
            host.querySelector(`:scope > .${STATUS_CLASS}`)?.remove();
            return;
        }

        let status = host.querySelector(`:scope > .${STATUS_CLASS}`);
        if (!status) {
            status = host.ownerDocument.createElement('span');
            status.className = STATUS_CLASS;
            host.appendChild(status);
        }
        status.classList.toggle('is-pending', state === 'pending');
        status.classList.toggle('is-failed', state === 'failed');
        status.textContent = state === 'pending' ? strings.generating : strings.failed;
    };

    /**
     * @param {HTMLImageElement} img
     * @param {{generating: string, failed: string}} strings
     */
    const enhanceImage = (img, strings) => {
        const state = imageState(img);
        if (!state) {
            return;
        }

        bustImageCache(img);

        const wrap = img.closest(`.${WRAP_CLASS}`);
        if (wrap) {
            ensureStatus(wrap, state, strings);
            return;
        }

        let frame = img.closest(`.${FRAME_CLASS}`);
        if (!frame) {
            frame = img.ownerDocument.createElement('span');
            frame.className = FRAME_CLASS;
            img.replaceWith(frame);
            frame.appendChild(img);
        }
        ensureStatus(frame, state, strings);
    };

    /**
     * Remove a status pill node if present.
     *
     * @param {Element|null|undefined} el
     */
    const removeStatusNode = (el) => {
        if (el && el.classList && el.classList.contains(STATUS_CLASS)) {
            el.remove();
        }
    };

    /**
     * Remove display-only frame/status around an image (e.g. after generation finishes).
     *
     * TinyMCE may move the status pill out of the frame or replace the img node, so
     * cleanup covers the frame, adjacent siblings, and leftover empty frames.
     *
     * @param {HTMLImageElement} img
     */
    const clearImageHost = (img) => {
        if (!isHtmlImage(img)) {
            return;
        }
        const frame = img.closest(`.${FRAME_CLASS}`);
        if (frame) {
            frame.querySelectorAll(`.${STATUS_CLASS}`).forEach((el) => el.remove());
            removeStatusNode(frame.previousElementSibling);
            removeStatusNode(frame.nextElementSibling);
            if (frame.contains(img)) {
                frame.replaceWith(img);
            } else if (!frame.querySelector('img')) {
                frame.remove();
            }
        }
        removeStatusNode(img.previousElementSibling);
        removeStatusNode(img.nextElementSibling);
        const parent = img.parentElement;
        if (parent) {
            parent.querySelectorAll(`:scope > .${STATUS_CLASS}`).forEach((el) => el.remove());
        }
    };

    /**
     * @param {ParentNode|Document} root
     * @param {{generating: string, failed: string}} strings
     */
    const enhanceTree = (root, strings) => {
        root.querySelectorAll(IMG_SELECTOR).forEach((img) => {
            if (isHtmlImage(img)) {
                enhanceImage(img, strings);
            }
        });
    };

    /**
     * Collect pending placeholder ids under the top document (bare imgs only).
     *
     * @returns {string[]}
     */
    const collectPendingPlaceholderIds = () => {
        const ids = [];
        document.querySelectorAll('img.dixeo-img-gen-pending').forEach((img) => {
            if (!isHtmlImage(img) || isInsideEditorWrap(img)) {
                return;
            }
            const id = placeholderIdFromImg(img);
            if (id && !ids.includes(id)) {
                ids.push(id);
            }
        });
        return ids;
    };

    /**
     * Find a live pending/failed img for a placeholder id (bare page imgs).
     *
     * @param {string} placeholderid
     * @param {string} filename
     * @returns {HTMLImageElement|null}
     */
    const findPageImage = (placeholderid, filename) => {
        let img = document.querySelector('img[data-dixeo-img-gen="' + placeholderid + '"]');
        if (!img && filename) {
            img = document.querySelector('img[src*="' + filename + '"]');
        }
        if (!isHtmlImage(img) || isInsideEditorWrap(img)) {
            return null;
        }
        return img;
    };

    /**
     * Strip pending classes when the server reports idle (no job).
     *
     * @param {{placeholderid: string, filename: string}} item
     * @returns {boolean}
     */
    const clearIdlePending = (item) => {
        const img = findPageImage(item.placeholderid, item.filename || '');
        if (!img) {
            return false;
        }
        img.classList.remove('dixeo-img-gen-pending', 'dixeo-img-gen-failed');
        clearImageHost(img);
        return true;
    };

    /**
     * Apply a terminal status payload to the live page DOM.
     *
     * @param {{placeholderid: string, status: string, imageurl: string, imgclass: string,
     *     contenthash: string, filename: string}} item
     * @param {{generating: string, failed: string}} strings
     * @returns {boolean}
     */
    const applyPageItem = (item, strings) => {
        if (item.status === 'idle') {
            return clearIdlePending(item);
        }

        const img = findPageImage(item.placeholderid, item.filename || '');
        if (!img) {
            return false;
        }

        let nextUrl = item.imageurl || '';
        if (item.contenthash && nextUrl) {
            nextUrl = appendImageRev(nextUrl, item.contenthash);
        }
        if (nextUrl) {
            img.setAttribute('src', nextUrl);
        }

        const nextClass = item.imgclass || 'img-fluid';
        img.setAttribute('class', nextClass);
        if (item.contenthash) {
            img.setAttribute('data-dixeo-contenthash', item.contenthash);
        } else {
            img.removeAttribute('data-dixeo-contenthash');
        }

        if (nextClass.indexOf('dixeo-img-gen-pending') === -1 &&
                nextClass.indexOf('dixeo-img-gen-failed') === -1) {
            clearImageHost(img);
        } else {
            enhanceImage(img, strings);
        }
        return true;
    };

    /**
     * Stop page-level placeholder polling.
     */
    const stopPagePolling = () => {
        if (pagePollTimer) {
            window.clearInterval(pagePollTimer);
            pagePollTimer = null;
        }
        pagePollStartedAt = 0;
    };

    /**
     * Poll server status for pending content images on the course page.
     *
     * @param {{generating: string, failed: string}} strings
     */
    const startPagePolling = (strings) => {
        const pollOnce = () => {
            if (pagePollStartedAt && (Date.now() - pagePollStartedAt) > MAX_POLL_MS) {
                stopPagePolling();
                return;
            }
            const ids = collectPendingPlaceholderIds();
            if (!ids.length) {
                stopPagePolling();
                return;
            }
            if (pagePollInFlight) {
                return;
            }
            pagePollInFlight = true;
            Ajax.call([{
                methodname: 'local_dixeo_get_content_image_status',
                args: {placeholderids: ids},
            }])[0].then((response) => {
                const items = response && response.items ? response.items : [];
                items.forEach((item) => {
                    if (item.status === 'pending' || item.status === 'processing') {
                        return;
                    }
                    applyPageItem(item, strings);
                });
                if (!collectPendingPlaceholderIds().length) {
                    stopPagePolling();
                }
                return undefined;
            }).catch(() => {
                // Keep polling on transient errors.
            }).then(() => {
                // jQuery deferreds from core/ajax may lack Promise.finally.
                pagePollInFlight = false;
            });
        };

        stopPagePolling();
        pagePollStartedAt = Date.now();
        pollOnce();
        pagePollTimer = window.setInterval(pollOnce, POLL_INTERVAL_MS);
    };

    /**
     * Enhance pending/failed images under root (document or element).
     *
     * @param {ParentNode|Document|null|undefined} [root]
     * @returns {Promise<void>}
     */
    const refresh = (root) => {
        return loadLabels().then((strings) => {
            const {searchRoot, doc} = resolveRoots(root);
            enhanceTree(searchRoot, strings);
            if (doc === document && collectPendingPlaceholderIds().length) {
                startPagePolling(strings);
            }
        });
    };

    /**
     * Enhance existing images and observe new ones under root.
     *
     * @param {ParentNode|Document|null|undefined} [root]
     */
    const init = (root) => {
        loadLabels().then((strings) => {
            const {searchRoot, observeRoot, doc} = resolveRoots(root);
            enhanceTree(searchRoot, strings);

            if (doc === document && collectPendingPlaceholderIds().length) {
                startPagePolling(strings);
            }

            if (typeof MutationObserver === 'undefined' || !observeRoot) {
                return;
            }
            if (observedDocs.has(doc)) {
                return;
            }
            observedDocs.add(doc);

            const observer = new MutationObserver((mutations) => {
                let addedPending = false;
                mutations.forEach((mutation) => {
                    mutation.addedNodes.forEach((node) => {
                        if (!(node instanceof Element)) {
                            return;
                        }
                        if (node.matches?.(IMG_SELECTOR) && isHtmlImage(node)) {
                            enhanceImage(node, strings);
                            if (doc === document &&
                                    node.classList.contains('dixeo-img-gen-pending') &&
                                    !isInsideEditorWrap(node)) {
                                addedPending = true;
                            }
                            return;
                        }
                        enhanceTree(node, strings);
                        if (doc === document) {
                            node.querySelectorAll?.('img.dixeo-img-gen-pending').forEach((img) => {
                                if (isHtmlImage(img) && !isInsideEditorWrap(img)) {
                                    addedPending = true;
                                }
                            });
                        }
                    });
                });
                if (addedPending) {
                    startPagePolling(strings);
                }
            });
            observer.observe(observeRoot, {childList: true, subtree: true});
        });
    };

    return {
        init,
        refresh,
        clearImageHost,
    };
});
