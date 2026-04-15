import {
    FileText,
    FileImage,
    FileSpreadsheet,
    FileVideo,
    FileAudio,
    FileArchive,
    FileCode,
    FileType,
    Presentation,
    Paperclip,
    type LucideIcon,
} from 'lucide-vue-next'

export interface AttachmentIconSpec {
    icon: LucideIcon
    color: string
}

/**
 * Resolve an icon + color pair for an attachment based on its MIME type
 * and (as a fallback) its filename extension. Colors follow the
 * conventional file-type hues users expect — red for PDF, green for
 * spreadsheets, blue for Word, etc. — so a row of mixed attachments
 * reads at a glance.
 *
 * MIME is the primary signal; the extension fallback exists because many
 * senders ship generic `application/octet-stream` and rely on the filename.
 */
export function useAttachmentIcon(
    mimeType: string,
    filename: string,
): AttachmentIconSpec {
    const mime = (mimeType || '').toLowerCase()
    const ext = extOf(filename)

    if (mime === 'application/pdf' || ext === 'pdf') {
        return { icon: FileType, color: 'text-red-600' }
    }

    if (
        mime.includes('spreadsheet')
        || mime.includes('excel')
        || mime === 'text/csv'
        || ['xls', 'xlsx', 'xlsm', 'csv', 'ods', 'numbers', 'tsv'].includes(ext)
    ) {
        return { icon: FileSpreadsheet, color: 'text-emerald-600' }
    }

    if (
        mime.includes('msword')
        || mime.includes('wordprocessingml')
        || mime === 'application/rtf'
        || ['doc', 'docx', 'odt', 'rtf', 'pages'].includes(ext)
    ) {
        return { icon: FileText, color: 'text-blue-600' }
    }

    if (
        mime.includes('presentation')
        || mime.includes('powerpoint')
        || ['ppt', 'pptx', 'odp', 'key'].includes(ext)
    ) {
        return { icon: Presentation, color: 'text-orange-600' }
    }

    if (
        mime.startsWith('image/')
        || ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'tiff', 'heic', 'avif'].includes(ext)
    ) {
        return { icon: FileImage, color: 'text-violet-600' }
    }

    if (
        mime.startsWith('video/')
        || ['mp4', 'mov', 'webm', 'mkv', 'avi', 'flv'].includes(ext)
    ) {
        return { icon: FileVideo, color: 'text-pink-600' }
    }

    if (
        mime.startsWith('audio/')
        || ['mp3', 'wav', 'ogg', 'flac', 'aac', 'm4a'].includes(ext)
    ) {
        return { icon: FileAudio, color: 'text-fuchsia-600' }
    }

    if (
        mime.includes('zip')
        || mime.includes('compressed')
        || mime.includes('x-tar')
        || mime.includes('x-rar')
        || mime.includes('x-7z')
        || ['zip', 'tar', 'gz', 'tgz', 'bz2', 'rar', '7z', 'xz'].includes(ext)
    ) {
        return { icon: FileArchive, color: 'text-amber-600' }
    }

    if (
        mime.startsWith('text/')
        || mime === 'application/json'
        || mime === 'application/xml'
        || mime.includes('javascript')
        || ['js', 'ts', 'tsx', 'jsx', 'json', 'xml', 'yaml', 'yml', 'html', 'css', 'scss', 'php', 'py', 'rb', 'go', 'rs', 'java', 'c', 'cpp', 'h', 'sh', 'sql', 'md', 'txt', 'log'].includes(ext)
    ) {
        return { icon: FileCode, color: 'text-sky-600' }
    }

    return { icon: Paperclip, color: 'text-on-surface-variant' }
}

function extOf(filename: string): string {
    if (!filename) return ''
    const i = filename.lastIndexOf('.')
    return i >= 0 ? filename.slice(i + 1).toLowerCase() : ''
}
