export const colors = {
  primary: '#E8407A',
  primaryDark: '#C22763',
  primaryLight: '#FDEBF3',
  peach: '#F4A988',
  wine: '#6B1F3A',
  ink: '#2C1620',
  muted: '#7D5967',
  cream: '#FFF7EF',
  surface: '#FFFFFF',
  border: '#F1E3E7',
  success: '#1BA86B',
  successLight: '#E2F6EC',
  warning: '#E8960C',
  warningLight: '#FDF3DC',
  danger: '#E03131',
  dangerLight: '#FDE8E8',
  info: '#3B82F6',
  infoLight: '#E8F1FD',
  text: '#4A3830',
  textLight: '#8A6A5C',
  white: '#FFFFFF',
} as const;

export const radius = {
  sm: 10,
  md: 14,
  lg: 20,
  xl: 28,
  full: 999,
} as const;

export const spacing = {
  xs: 4,
  sm: 8,
  md: 14,
  lg: 20,
  xl: 28,
} as const;

export const typography = {
  title: 26,
  h1: 22,
  h2: 18,
  h3: 16,
  body: 15,
  small: 13,
  tiny: 11,
} as const;

export const shadow = {
  card: {
    shadowColor: '#6B1F3A',
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.12,
    shadowRadius: 14,
    elevation: 3,
  },
  float: {
    shadowColor: '#6B1F3A',
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.22,
    shadowRadius: 18,
    elevation: 8,
  },
} as const;