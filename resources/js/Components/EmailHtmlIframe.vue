<template>
    <iframe
        ref="frame"
        class="w-full rounded-lg ring-1 ring-slate-200"
        :sandbox="sandbox"
        :srcdoc="srcdoc"
        @load="resize"
    />
</template>

<script setup>
import {ref, watch, onMounted} from 'vue'

const props = defineProps({
    html: {type: String, default: ''},
})

const frame = ref(null)
const sandbox = 'allow-same-origin' // no "allow-scripts" to keep scripts disabled

// Minimal CSS shell injected around the email to normalize layout & make it responsive
const SHELL_CSS = `
  :root { color-scheme: light dark; }
  html, body { margin: 0; padding: 0; }
  body { font: 14px/1.5 system-ui, -apple-system, Segoe UI, Roboto, sans-serif; background: #fff; }
  img, video, iframe { max-width: 100%; height: auto; }
  table { max-width: 100%; border-collapse: collapse; }
  pre, code { white-space: pre-wrap; word-wrap: break-word; }
`

function sanitizeHtml(html) {
    // keep your sanitizer here; regex is fragile—consider DOMPurify if possible.
    return String(html)
        .replace(/<script[\s\S]*?>[\s\S]*?<\/script>/gi, '')
        .replace(/\son\w+="[^"]*"/gi, '')
        .replace(/\s(href|src)=["']javascript:[^"']*["']/gi, '$1="#"')
}

const srcdoc = computed(() => {
    const safe = sanitizeHtml(props.html)
    return `<!doctype html>
<html>
<head><meta charset="utf-8"><style>${SHELL_CSS}</style></head>
<body>${safe}</body>
</html>`
})

function resize() {
    const el = frame.value
    if (!el) return
    try {
        const docEl = el.contentDocument?.documentElement
        const body = el.contentDocument?.body
        if (!docEl || !body) return

        // Initial
        el.style.height = Math.max(body.scrollHeight, docEl.scrollHeight) + 'px'

        // Observe future changes (images loading, fonts, etc.)
        const ro = new ResizeObserver(() => {
            el.style.height = Math.max(body.scrollHeight, docEl.scrollHeight) + 'px'
        })
        ro.observe(docEl)
        ro.observe(body)
    } catch { /* cross-origin guard (shouldn’t happen with srcdoc+allow-same-origin) */
    }
}

onMounted(resize)
watch(() => props.html, () => {
    // srcdoc will update; height will recompute onload
})
</script>
