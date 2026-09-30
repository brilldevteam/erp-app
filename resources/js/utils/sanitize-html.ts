import DOMPurify from 'dompurify';

// Links that open in a new tab keep doing so, without giving the opened page access to this window.
DOMPurify.addHook('afterSanitizeAttributes', (node) => {
    if (node.tagName === 'A' && node.getAttribute('target') === '_blank') {
        node.setAttribute('rel', 'noopener noreferrer');
    }
});

// Rich text written by users is rendered as HTML: remove scripts, event handlers and javascript: links
// while keeping normal formatting (headings, lists, links, tables, inline styles).
export const sanitizeHtml = (html?: string | null): string => DOMPurify.sanitize(html ?? '', { ADD_ATTR: ['target'] });
