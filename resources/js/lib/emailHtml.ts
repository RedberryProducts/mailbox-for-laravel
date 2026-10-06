/**
 * DOM-based handling of captured email HTML.
 *
 * Email bodies are parsed with DOMParser (which never runs scripts) and
 * cleaned on the parsed tree instead of with regexes, which miss variants
 * like `</script >` or single-quoted event handlers. The preview iframe's
 * sandbox (no `allow-scripts`) remains the primary defence; this keeps the
 * markup itself free of active content too.
 */

/** Elements dropped entirely, including their content. */
const BLOCKED_ELEMENTS = [
    'script',
    'noscript',
    'template',
    'iframe',
    'frame',
    'frameset',
    'object',
    'embed',
    'applet',
    'base',
    'meta',
    'title',
    'animate',
    'set',
]

/** Attributes that carry a URL a browser may navigate to or load. */
const URL_ATTRIBUTES = ['href', 'src', 'action', 'formaction', 'xlink:href', 'background', 'poster']

/** Elements whose text is not part of the readable message. */
const NON_CONTENT_ELEMENTS = ['head', 'style', 'script', 'noscript', 'template', 'title']

function parse(html: string): Document {
    return new DOMParser().parseFromString(html, 'text/html')
}

/**
 * True for javascript:, vbscript: and non-image data: URLs. Browsers ignore
 * control characters and whitespace inside the scheme, so strip them first.
 */
function isUnsafeUrl(value: string): boolean {
    const compact = value.replace(/[\u0000- \u007f]+/g, '').toLowerCase()

    return compact.startsWith('javascript:')
        || compact.startsWith('vbscript:')
        || (compact.startsWith('data:') && !compact.startsWith('data:image/'))
}

function sanitizeTree(doc: Document): void {
    doc.querySelectorAll(BLOCKED_ELEMENTS.join(',')).forEach((node) => node.remove())

    doc.querySelectorAll('*').forEach((element) => {
        for (const attribute of Array.from(element.attributes)) {
            const name = attribute.name.toLowerCase()

            if (name.startsWith('on')
                || (URL_ATTRIBUTES.includes(name) && isUnsafeUrl(attribute.value))) {
                element.removeAttribute(attribute.name)
            }
        }

        // Open links outside the sandboxed preview, never in the dashboard tab.
        if (element.tagName === 'A') {
            element.setAttribute('target', '_blank')
            element.setAttribute('rel', 'noopener noreferrer nofollow ugc')
        }
    })
}

/**
 * Build the srcdoc for the preview iframe: the shell styles first, then the
 * email's own head styles, with the email's body attributes and content
 * carried over after sanitizing.
 */
export function buildEmailDocument(html: string, shellCss: string): string {
    const source = parse(html)
    sanitizeTree(source)

    const output = document.implementation.createHTMLDocument('')

    const charset = output.createElement('meta')
    charset.setAttribute('charset', 'utf-8')

    const shell = output.createElement('style')
    shell.textContent = shellCss

    output.head.replaceChildren(charset, shell)

    for (const node of Array.from(source.head.childNodes)) {
        output.head.append(output.importNode(node, true))
    }

    for (const attribute of Array.from(source.body.attributes)) {
        output.body.setAttribute(attribute.name, attribute.value)
    }

    for (const node of Array.from(source.body.childNodes)) {
        output.body.append(output.importNode(node, true))
    }

    return `<!doctype html>\n${output.documentElement.outerHTML}`
}

/**
 * The readable text of an HTML email, for list snippets. Text from separate
 * elements is joined with spaces so words from adjacent blocks don't merge.
 */
export function htmlToText(html: string): string {
    const doc = parse(html)
    doc.querySelectorAll(NON_CONTENT_ELEMENTS.join(',')).forEach((node) => node.remove())

    const walker = doc.createTreeWalker(doc.body, NodeFilter.SHOW_TEXT)
    const parts: string[] = []

    while (walker.nextNode()) {
        parts.push(walker.currentNode.textContent ?? '')
    }

    return parts.join(' ')
}
