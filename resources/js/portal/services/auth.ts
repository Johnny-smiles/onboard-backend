import api from './api';
export async function login(email:string, password:string) {
  const { data } = await api.post('/login', { email, password });
  if (data?.token) localStorage.setItem('token', data.token);
  localStorage.setItem('user', JSON.stringify(data?.user||{}));
  return data;
}
export function currentUser(){ try{return JSON.parse(localStorage.getItem('user')||'{}')}catch{return{}} }
export function isAdmin(){ return currentUser()?.role === 'admin'; }
export function logout(){ try{api.post('/logout')}catch{} localStorage.removeItem('token'); localStorage.removeItem('user'); }
