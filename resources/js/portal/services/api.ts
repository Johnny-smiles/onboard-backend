import axios from 'axios';
const API_BASE = (window as any).__API_BASE_URL__ || 'http://127.0.0.1:8000/api/v1';
const api = axios.create({ baseURL: API_BASE, timeout: 20000 });
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token');
  if (token) { (config.headers = config.headers || {} as any).Authorization = `Bearer ${token}`; }
  return config;
});
export default api;
