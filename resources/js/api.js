import axios from "axios"

// Read CSRF token from the Blade-injected meta tag
const csrfToken =
    document.querySelector('meta[name="csrf-token"]')?.getAttribute("content")

const mailboxPrefix =
    document.querySelector('meta[name="mailbox-prefix"]')?.getAttribute("content")

const api = axios.create({
    baseURL: `${mailboxPrefix}`,
    withCredentials: true,
    headers: {
        "X-Requested-With": "XMLHttpRequest",
        "X-CSRF-TOKEN": csrfToken,
    },
})

// Optional: add interceptor for errors/logging
api.interceptors.response.use(
    (response) => response,
    (error) => {
        console.error("API error:", error.response || error.message)
        return Promise.reject(error)
    }
)

// Convenience methods
export const get = (url, config = {}) => api.get(url, config)
export const post = (url, data = {}, config = {}) => api.post(url, data, config)
export const put = (url, data = {}, config = {}) => api.put(url, data, config)
export const del = (url, config = {}) => api.delete(url, config)

export default api
