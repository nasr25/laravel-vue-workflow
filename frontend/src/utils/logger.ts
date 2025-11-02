/**
 * Error Logger Utility
 * Use this to log errors throughout your Vue application
 */

interface LogOptions {
  message: string
  error?: Error | unknown
  context?: Record<string, any>
}

class Logger {
  /**
   * Log error to console and optionally send to backend
   */
  error(options: LogOptions): void {
    console.error('Error:', options.message)

    if (options.error) {
      console.error('Error Details:', options.error)
    }

    if (options.context) {
      console.error('Context:', options.context)
    }

    // Optionally send to backend API
    this.sendToBackend('error', options)
  }

  /**
   * Log warning
   */
  warn(options: LogOptions): void {
    console.warn('Warning:', options.message)

    if (options.context) {
      console.warn('Context:', options.context)
    }

    this.sendToBackend('warning', options)
  }

  /**
   * Log info
   */
  info(options: LogOptions): void {
    console.info('Info:', options.message)

    if (options.context) {
      console.info('Context:', options.context)
    }
  }

  /**
   * Send log to backend (optional)
   */
  private sendToBackend(level: string, options: LogOptions): void {
    // Uncomment and configure when ready to send to backend
    /*
    fetch('http://localhost:8000/api/logs', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        level,
        message: options.message,
        error: options.error instanceof Error ? {
          message: options.error.message,
          stack: options.error.stack,
        } : options.error,
        context: options.context,
        timestamp: new Date().toISOString(),
      }),
    }).catch(err => {
      console.error('Failed to send log to backend:', err)
    })
    */
  }
}

export const logger = new Logger()

/**
 * Example usage:
 *
 * import { logger } from '@/utils/logger'
 *
 * try {
 *   // your code
 * } catch (error) {
 *   logger.error({
 *     message: 'Failed to fetch data',
 *     error,
 *     context: { userId: 123 }
 *   })
 * }
 */
