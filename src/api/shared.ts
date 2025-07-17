// Shared API utilities for Cache Hive frontend
export {};

// Ensure wpApiSettings and chToolbar are available (WordPress localizes these)
declare global {
  interface Window {
    wpApiSettings: {
      root: string;
      nonce: string;
      [key: string]: any;
    };
    chToolbar: {
      root: string;
      nonce: string;
      page_url: string;
    };
  }
}

export const wpApiSettings = window.wpApiSettings || {
  root: "/wp-json/",
  nonce: "",
};

export const chToolbarSettings = window.chToolbar || {
  root: "/wp-json/",
  nonce: "",
  page_url: "",
};
