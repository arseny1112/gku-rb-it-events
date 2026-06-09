/// <reference types="vite/client" />

interface ImportMetaEnv {
    readonly VITE_API_URL: string;
    readonly VITE_VK_GROUP_ID: string;
    readonly VITE_VK_CLIENT_ID: string;
    readonly VITE_REDIRECT_URL: string;
    readonly VITE_VK_DIALOG_URL: string;
  }
  
  interface ImportMeta {
    readonly env: ImportMetaEnv;
  }