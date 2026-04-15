<template>
    <iframe
        ref="frame"
        class="w-full rounded-lg ring-1 ring-slate-200"
        :sandbox="sandbox"
        :srcdoc="srcdoc"
        @load="resize"
    />
</template>

<script setup lang="ts">
import {ref, computed, onBeforeUnmount} from 'vue'

const props = defineProps<{
    html: string | null | undefined
    // When the message is text-only, pass the text body here. If `html`
    // is empty we wrap this in a minimal HTML scaffold so the HTML tab
    // still renders something sensible (matches how Mailtrap shows
    // text-only mail in its HTML preview).
    textFallback?: string | null
}>()

const frame = ref<HTMLIFrameElement | null>(null)
let resizeObserver: ResizeObserver | null = null

// NOTE: you currently allow scripts; if you want them disabled, remove `allow-scripts`
const sandbox =
    'allow-same-origin allow-forms allow-popups allow-popups-to-escape-sandbox allow-top-navigation-by-user-activation'

const SHELL_CSS = `
  :root { color-scheme: light dark; }
  html, body { margin: 0; padding: 0; }
  body { font: 14px/1.5 system-ui, -apple-system, Segoe UI, Roboto, sans-serif; background: #fff; }
  img, video, iframe { max-width: 100%; height: auto; }
  table { max-width: 100%; border-collapse: collapse; }
  pre, code { white-space: pre-wrap; word-wrap: break-word; }
`

function rewriteLinksToRealAnchors(html: string): string {
    const aTag = /<a\b([^>]*)>/gi
    return html.replace(aTag, (m, attrs) => {
        let newAttrs = attrs
            .replace(/\starget="[^"]*"/gi, '')
            .replace(/\srel="[^"]*"/gi, '')
        newAttrs += ' target="_blank" rel="noopener noreferrer nofollow ugc"'
        return `<a ${newAttrs}>`
    })
}

function sanitizeHtml(html: string): string {
    return String(html)
        .replace(/<script[\s\S]*?>[\s\S]*?<\/script>/gi, '')
        .replace(/\son\w+="[^"]*"/gi, '')
        .replace(/\s(href|src)=["']javascript:[^"']*["']/gi, '$1="#"')
}

function escapeHtml(s: string): string {
    return s
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;')
}

// Wrap a plain-text body in a <pre>-styled scaffold so links stay clickable
// and whitespace is preserved without leaking into the page chrome.
function textToHtml(text: string): string {
    return `<pre style="margin:0;padding:16px;white-space:pre-wrap;word-wrap:break-word;font:14px/1.6 ui-monospace,SFMono-Regular,Menlo,monospace;">${escapeHtml(text)}</pre>`
}

const srcdoc = computed(() => {
    const html = props.html ?? ''
    const text = props.textFallback ?? ''

    const body = html.trim() !== ''
        ? rewriteLinksToRealAnchors(sanitizeHtml(html))
        : text.trim() !== ''
            ? textToHtml(text)
            : '<p style="padding:24px;color:#888;font:14px/1.5 system-ui,sans-serif;">This message has no body.</p>'

    return `<!doctype html>
<html>
<head><meta charset="utf-8"><style>${SHELL_CSS}</style></head>
<body>${body}</body>
</html>`
})

function resize() {
    const el = frame.value
    if (!el) return

    const doc = el.contentDocument
    if (!doc) return

    const docEl = doc.documentElement
    const body = doc.body
    if (!docEl || !body) return

    const setHeight = () => {
        frame.value?.contentWindow?.innerHeight
        el.style.height = Math.max(body.scrollHeight, docEl.scrollHeight) + 'px'
    }

    setHeight()

    if (resizeObserver) {
        resizeObserver.disconnect()
    }

    resizeObserver = new ResizeObserver(setHeight)
    resizeObserver.observe(docEl)
    resizeObserver.observe(body)
}

onBeforeUnmount(() => {
    if (resizeObserver) {
        resizeObserver.disconnect()
        resizeObserver = null
    }
})
</script>
