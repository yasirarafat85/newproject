// ছোট DOM হেল্পার — jQuery-র বদলে যা যা সত্যিই দরকার।
export const $  = (selector, scope = document) => scope.querySelector(selector);
export const $$ = (selector, scope = document) => [...scope.querySelectorAll(selector)];

export function on(target, event, handler, options) {
  target?.addEventListener(event, handler, options);
  return () => target?.removeEventListener(event, handler, options);
}

/** ইভেন্ট ডেলিগেশন — পরে যোগ হওয়া এলিমেন্টেও কাজ করে। */
export function delegate(root, selector, event, handler) {
  return on(root, event, (e) => {
    const match = e.target.closest(selector);
    if (match && root.contains(match)) handler(e, match);
  });
}

/** টেক্সট থেকে এলিমেন্ট — innerHTML এড়াতে। */
export function el(tag, props = {}, children = []) {
  const node = document.createElement(tag);
  for (const [key, value] of Object.entries(props)) {
    if (key === 'class') node.className = value;
    else if (key === 'text') node.textContent = value;
    else if (key.startsWith('on')) node.addEventListener(key.slice(2).toLowerCase(), value);
    else node.setAttribute(key, value);
  }
  for (const child of [].concat(children)) {
    node.append(child instanceof Node ? child : document.createTextNode(child));
  }
  return node;
}
