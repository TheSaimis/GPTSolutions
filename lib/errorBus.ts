export type Message = {
    title: string;
    message: string;
    backgroundColor?: string;
  };
  
  const bus = new EventTarget();
  const EVENT = "app-error";
  
  export function emitError(err?: Partial<Message>) {
    const payload: Message = {
      title: err?.title ?? "Klaida",
      message: err?.message ?? "Nežinoma klaida",
      backgroundColor: err?.backgroundColor,
    };

    bus.dispatchEvent(new CustomEvent<Message>(EVENT, { detail: payload }));
  }
  
  export function onError(handler: (err: Message) => void) {
    const listener = (e: Event) => handler((e as CustomEvent<Message>).detail);
    bus.addEventListener(EVENT, listener);
    return () => bus.removeEventListener(EVENT, listener);
  }