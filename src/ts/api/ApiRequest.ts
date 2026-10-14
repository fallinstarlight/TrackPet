type HttpMethod = 'GET' | 'POST' | 'PATCH' | 'DELETE';

export class ApiRequest<TResponse = unknown, TBody = unknown > {
    
    public body: TBody;

    constructor(
        public readonly resource: string,
        public readonly method: HttpMethod,
        body?: TBody,
        public readonly searchParams: Record<string, string> = {},
    ) {
        this.body = body as TBody;
    }

    get url(): string {
        const params = new URLSearchParams({
            resource: this.resource,
            ...this.searchParams,
        });
        return `api/endpoint.php?${params}`;
    }

    async send(): Promise<TResponse> {
        const init: RequestInit = { method: this.method, credentials: 'include' };
        if (this.method !== 'GET' && this.method !== 'DELETE' && this.body !== undefined) {
            init.headers = { 'Content-Type': 'application/json' };
            init.body = JSON.stringify(this.body);
        }

        try {
            const res = await fetch(this.url, init);
            const text = await res.text();
            try {
                return JSON.parse(text) as TResponse;
            } catch {
                return { status: 'error', message: 'Invalid response from server' } as unknown as TResponse;
            }
        } catch {
            return { status: 'error', message: 'Network error, please try again' } as unknown as TResponse;
        }
    }
}