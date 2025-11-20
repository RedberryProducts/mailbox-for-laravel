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
    html: string
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

// 🔥 now srcdoc depends directly on props.html
const srcdoc = computed(() => {
    const safe = sanitizeHtml(props.html || '')
    const normalized = rewriteLinksToRealAnchors(safe)

    return `<!doctype html>
<html>
<head><meta charset="utf-8"><style>${SHELL_CSS}</style></head>
<body>${normalized}</body>
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
