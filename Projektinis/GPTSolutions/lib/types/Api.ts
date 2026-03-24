export type ApiStatus<T = unknown> = {
    status: "SUCCESS" | "ERROR";
    error: string;
    data?: T;
  };