// src/config.ts

const protocol = window.location.protocol; 
const host = window.location.host;       

export const config = {
  VK_CLIENT_ID: import.meta.env.VITE_VK_CLIENT_ID,
  
  REDIRECT_URL: `${protocol}//${host}/event_organizer/backend/auth/vk-callback.php`,
  API_URL: `${protocol}//${host}/event_organizer/backend`,
};