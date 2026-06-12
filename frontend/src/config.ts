// src/config.ts

const protocol = window.location.protocol; 
const host = window.location.host;

const API_URL = `${protocol}//${host}/event_organizer/backend`;

const OAUTH_REDIRECT_URL = host.includes('localhost') 
  ? 'http://localhost/event_organizer/backend/auth/vk-callback.php'  // Локально
  : `${protocol}//${host}/event_organizer/backend/auth/vk-callback.php`;  // Прод

export const config = {
  VK_CLIENT_ID: import.meta.env.VITE_VK_CLIENT_ID,
  REDIRECT_URL: OAUTH_REDIRECT_URL,
  API_URL: API_URL,
};