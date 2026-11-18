type HttpMethod = 'GET' | 'POST' | 'PATCH' | 'DELETE';

export interface ApiResponse<T> { status: 'success' | 'error'; message: string; data: T; }

export class ApiRequest<TResponse = unknown, TBody = unknown> {
    public statusCode = 0;
    constructor(
        public readonly resource: string,
        public readonly method: HttpMethod,
        public body?: TBody,
        public readonly searchParams: Record<string, string> = {},
    ) {}

    get url(): string {
        const params = new URLSearchParams({ resource: this.resource, ...this.searchParams });
        return `api/endpoint.php?${params}`;
    }

    async send(signal?: AbortSignal): Promise<TResponse> {
        this.statusCode = 0;
        const controller = new AbortController();
        const abort = () => controller.abort();
        if (signal?.aborted) controller.abort();
        signal?.addEventListener('abort', abort, { once: true });
        const timeout = setTimeout(abort, 12000);
        const init: RequestInit = { method: this.method, credentials: 'same-origin', signal: controller.signal };
        if (this.method !== 'GET' && this.body !== undefined) {
            if (this.body instanceof FormData) {
                init.body = this.body;
            } else {
                init.headers = { 'Content-Type': 'application/json' };
                init.body = JSON.stringify(this.body);
            }
        }
        try {
            const res = await fetch(this.url, init);
            this.statusCode = res.status;
            const result: unknown = await res.json();
            if (!result || typeof result !== 'object' || !('status' in result) || !['success', 'error'].includes(String(result.status)) || !('message' in result) || typeof result.message !== 'string' || !('data' in result)) throw new Error('Invalid JSON');
            return result as TResponse;
        } catch {
            return { status: 'error', message: this.statusCode ? 'El servidor devolvió una respuesta inválida.' : 'No pudimos conectar con el servidor. Intenta de nuevo.', data: [] } as TResponse;
        } finally {
            clearTimeout(timeout);
            signal?.removeEventListener('abort', abort);
        }
    }
}
