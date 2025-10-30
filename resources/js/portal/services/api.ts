import axios from 'axios';
const API_BASE = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api/v1';
const api = axios.create({ 
  baseURL: API_BASE, 
  timeout: 20000,
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  }
});
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token');
  if (token) { (config.headers = config.headers || {} as any).Authorization = `Bearer ${token}`; }
  return config;
});
export default api;
