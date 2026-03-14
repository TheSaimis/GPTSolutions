export type ApiStatus<T = unknown> = {
    status: "SUCCESS" | "ERROR";
    data?: T;
  };