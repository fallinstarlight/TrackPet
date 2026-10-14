import { ApiRequest } from './api/ApiRequest.js';

interface LogoutResponse { 
    status: string, 
    message: string,
    data: { }
};

const logout = new ApiRequest<LogoutResponse>('logout', 'GET');