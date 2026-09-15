import React, { createContext, useCallback, useContext, useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { colors, typography } from '../theme';

export type ToastType = 'success' | 'error' | 'info' | 'warning';

interface ToastItem {
  id: number;
  message: string;
  type: ToastType;
}

interface ToastContextData {
  show: (message: string, type?: ToastType) => void;
}

const ToastContext = createContext<ToastContextData>({ show: () => {} });

let toastId = 0;

const TOAST_COLORS: Record<ToastType, string> = {
  success: colors.success,
  error: colors.danger,
  warning: colors.warning,
  info: colors.info,
};

const TOAST_ICONS: Record<ToastType, string> = {
  success: '✓',
  error: '✕',
  warning: '!',
  info: 'ℹ',
};

export const ToastProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [toasts, setToasts] = useState<ToastItem[]>([]);

  const show = useCallback((message: string, type: ToastType = 'info') => {
    const id = ++toastId;
    setToasts((prev) => [...prev.slice(-2), { id, message, type }]);
    setTimeout(() => {
      setToasts((prev) => prev.filter((t) => t.id !== id));
    }, 3800);
  }, []);

  return (
    <ToastContext.Provider value={{ show }}>
      {children}
      <View pointerEvents="none" style={styles.stack}>
        {toasts.map((toast) => (
          <View
            key={toast.id}
            style={[styles.bubble, { backgroundColor: TOAST_COLORS[toast.type] }]}
          >
            <Text style={styles.icon}>{TOAST_ICONS[toast.type]}</Text>
            <Text style={styles.text}>{toast.message}</Text>
          </View>
        ))}
      </View>
    </ToastContext.Provider>
  );
};

const styles = StyleSheet.create({
  stack: {
    position: 'absolute',
    top: 58,
    left: 16,
    right: 16,
    zIndex: 9999,
  },
  bubble: {
    flexDirection: 'row',
    alignItems: 'center',
    borderRadius: 14,
    paddingVertical: 12,
    paddingHorizontal: 16,
    marginBottom: 8,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.16,
    shadowRadius: 8,
    elevation: 6,
  },
  icon: {
    color: '#fff',
    fontSize: typography.body,
    fontWeight: '800',
    marginRight: 10,
  },
  text: {
    color: '#fff',
    fontSize: typography.body,
    fontWeight: '600',
    flex: 1,
  },
});

export const useToast = (): ToastContextData => useContext(ToastContext);