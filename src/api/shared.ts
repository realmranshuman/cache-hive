// Shared API utilities for Cache Hive frontend
export {};

// Ensure wpApiSettings is available (WordPress localizes this)
declare global {
  interface Window {
    wpApiSettings: {
      root: string;
      nonce: string;
      [key: string]: any;
    };
  }
}
export const wpApiSettings = window.wpApiSettings || {
  root: "/wp-json/",
  nonce: "",
};
