import { useMutation, useQuery } from '@tanstack/react-query';
import { authFetch } from '../auth';

export interface InitiatePaymentRequest {
  plan_id: string;
  amount: number;
  phone_number: string;
  provider: string; // MTN, AIRTEL
  idempotency_key: string;
}

export interface PaymentStatusResponse {
  uuid: string;
  amount: number;
  currency: string;
  phone_number: string;
  provider: string;
  status: 'created' | 'submitted' | 'pending' | 'processing' | 'completed' | 'failed' | 'expired' | 'cancelled';
  error_message: string | null;
}

/**
 * Initiates MoMo payment.
 */
export async function initiatePayment(fields: InitiatePaymentRequest): Promise<{ payment_uuid: string; status: string }> {
  return authFetch('/payments/initiate', {
    method: 'POST',
    body: JSON.stringify(fields),
  });
}

/**
 * Polls status of a payment.
 */
export async function getPaymentStatus(paymentUuid: string): Promise<PaymentStatusResponse> {
  return authFetch<PaymentStatusResponse>(`/payments/${paymentUuid}/status`, {
    method: 'GET',
  });
}

/**
 * Hook to initiate payment transaction.
 */
export function useInitiatePaymentMutation() {
  return useMutation({
    mutationFn: initiatePayment,
  });
}

/**
 * Hook to query status of a payment (with polling interval if not completed).
 */
export function usePaymentStatus(paymentUuid: string | null) {
  return useQuery<PaymentStatusResponse>({
    queryKey: ['paymentStatus', paymentUuid],
    queryFn: () => getPaymentStatus(paymentUuid!),
    enabled: !!paymentUuid,
    refetchInterval: (query) => {
      const data = query.state.data;
      if (data && ['completed', 'failed', 'expired', 'cancelled'].includes(data.status)) {
        return false; // Stop polling when payment reaches a terminal state
      }
      return 3000; // Poll every 3 seconds while pending/processing
    },
  });
}

export interface Plan {
  uuid: string;
  slug: string;
  name: string;
  monthly_price: number;
  annual_price: number;
  currency: string;
  storage_limit: number;
  video_limit: number;
  gallery_limit: number;
  team_limit: number;
}

export async function getPlans(): Promise<Plan[]> {
  const res = await authFetch<{ data: Plan[] }>('/plans', {
    method: 'GET',
  });
  return res.data;
}

export function usePlans() {
  return useQuery<Plan[]>({
    queryKey: ['plans'],
    queryFn: getPlans,
  });
}
