// Utility to wrap a promise for React Suspense
export function wrapPromise<T>(promise: Promise<T>): { read: () => T } {
  let status: "pending" | "success" | "error" = "pending";
  let result: T;
  let error: any;

  const suspender = promise.then(
    (r: T) => {
      status = "success";
      result = r;
    },
    (e: any) => {
      status = "error";
      error = e;
    }
  );

  return {
    read(): T {
      if (status === "pending") {
        throw suspender;
      } else if (status === "error") {
        throw error;
      } else if (status === "success") {
        return result;
      }
      throw new Error("Promise wrapper in invalid state");
    },
  };
}
