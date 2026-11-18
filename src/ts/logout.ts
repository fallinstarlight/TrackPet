import { ApiRequest, type ApiResponse } from './api/ApiRequest.js';

export async function logout(): Promise<ApiResponse<unknown>> {
    const request = new ApiRequest<ApiResponse<unknown>>('logout', 'POST');
    const response = await request.send();
    if (response.status === 'success' || request.statusCode === 401) window.location.replace('login.html');
    return response;
}
