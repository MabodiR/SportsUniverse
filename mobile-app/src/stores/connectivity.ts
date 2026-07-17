import { create } from 'zustand';

type ConnectivityState = {
  unavailable: boolean;
  checking: boolean;
  lastFailureAt?: number;
  markAvailable: () => void;
  markUnavailable: () => void;
  setChecking: (checking: boolean) => void;
};

export const useConnectivityStore = create<ConnectivityState>(set => ({
  unavailable: false,
  checking: false,
  markAvailable: () => set({ unavailable: false, checking: false }),
  markUnavailable: () => set({ unavailable: true, checking: false, lastFailureAt: Date.now() }),
  setChecking: checking => set({ checking }),
}));
