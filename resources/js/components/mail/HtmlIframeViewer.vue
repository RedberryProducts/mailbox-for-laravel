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
import {buildEmailDocument} from '@/lib/emailHtml'

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

// Scripts in captured mail must never run: `allow-scripts` is deliberately
// absent, and must stay absent while `allow-same-origin` is set, since the
// two together would let a message script reach the dashboard's origin.
const sandbox =
    'allow-same-origin allow-forms allow-popups allow-popups-to-escape-sandbox allow-top-navigation-by-user-activation'

// Force a light color scheme inside the iframe so user-agent defaults
// (which honor the host OS dark-mode preference) don't flip text to
// white against our explicit white background — the bug was: text-only
// mail rendered white-on-white when the OS was in dark mode.
const SHELL_CSS = `
  :root { color-scheme: light; }
  html, body { margin: 0; padding: 0; }
  /* display: flow-root establishes a block formatting context so child
     margins can't collapse through <body>. Without it, the first/last
     child's default UA margins escape to the viewport — rendered as
     whitespace but NOT counted in body.scrollHeight — and the iframe
     resize logic below clips the last ~30px of text-only emails. */
  body { display: flow-root; font: 14px/1.5 system-ui, -apple-system, Segoe UI, Roboto, sans-serif; background: #fff; color: #111; }
  img, video, iframe { max-width: 100%; height: auto; }
  table { max-width: 100%; border-collapse: collapse; }
  pre, code { white-space: pre-wrap; word-wrap: break-word; }
`

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
        ? html
        : text.trim() !== ''
            ? textToHtml(text)
            : '<p style="padding:24px;color:#888;font:14px/1.5 system-ui,sans-serif;">This message has no body.</p>'

    return buildEmailDocument(body, SHELL_CSS)
})

function resize() {
    const el = frame.value
    if (!el) return

    const doc = el.contentDocument
    if (!doc) return

    const docEl = doc.documentElement
    const body = doc.body
    if (!docEl || !body) return

    // Resize the iframe to fit its content. The previous version only
    // ever grew because `documentElement.scrollHeight` reflects the
    // *current* iframe viewport, not the natural content height — so
    // once the iframe was inflated to 800px, switching to a smaller
    // email would still report 800px. Fix: collapse the iframe to 0,
    // force a reflow, then read `body.scrollHeight` (which is now the
    // natural unconstrained content height) and size to it.
    const setHeight = () => {
        el.style.height = '0px'
        void el.offsetHeight

        el.style.height = body.scrollHeight + 'px'
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
