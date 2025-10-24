import axios from 'axios';

const api = axios.create({
  baseURL: 'http://127.0.0.1:8000/api',
  headers: {
    'Content-Type': 'application/json'
  }
});

// Payment API functions
export const paymentAPI = {
  // Get all payments
  getAll: () => api.get('/payments'),
  
  // Get payment journal
  getJournal: () => api.get('/payments/journal'),
  
  // Get specific payment
  getById: (id) => api.get(`/payments/${id}`),
  
  // Create new payment
  create: (paymentData) => api.post('/payments', paymentData),
  
  // Update payment
  update: (id, paymentData) => api.put(`/payments/${id}`, paymentData),
  
  // Delete payment
  delete: (id) => api.delete(`/payments/${id}`)
};

// Taxpayer API functions
export const taxpayerAPI = {
  // Search taxpayers
  search: (params) => api.get('/ownersearch', { params }),
  
  // Get taxpayer by LOCAL_TIN
  getByTin: (localTin) => api.get(`/taxpayers/${localTin}`)
};

// Tax Due API functions
export const taxDueAPI = {
  // Get tax due by LOCAL_TIN
  getTaxDue: (localTin) => api.get(`/tax-due/${localTin}`),
  
  // Get properties by LOCAL_TIN
  getProperties: (localTin) => api.get(`/get-tax-due/properties/${localTin}`),

  // Get tax due entries for a specific TD under a LOCAL_TIN
  getTaxDueByTdno: (localTin, tdno) => api.get(`/tax-due/${localTin}/${encodeURIComponent(tdno)}`),

  // Initialize taxpayer debit (compute penalties/discounts)
  initializeTaxpayerDebit: (localTin) => api.post(`/tax-due/initialize/${localTin}`),

  // Get assessment details for Manual Debit page
  getAssessmentDetails: (localTin) => api.get(`/tax-due/assessments/${localTin}`),

  // Button functions
  computePenaltyDiscount: (localTin, postingDate = null) => api.post(`/tax-due/compute-pen/${localTin}`, { posting_date: postingDate }),
  removePenaltyDiscount: (localTin) => api.delete(`/tax-due/remove-pen/${localTin}`),
  addTaxCredit: (creditData) => api.post('/tax-due/add-credit', creditData),
  removeCredits: (localTin) => api.delete(`/tax-due/remove-credits/${localTin}`)
};

// Property API functions
export const propertyAPI = {
  // Get all properties
  getAll: () => api.get('/properties'),
  
  // Search properties
  search: (params) => api.get('/properties/search', { params })
};

// Barangay API functions
export const barangayAPI = {
  // Get all barangays
  getAll: () => api.get('/barangays')
};

export default api;