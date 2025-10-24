import React, { useState, useEffect } from 'react';
import api from '../services/api';

export default function PaymentList({ search }) {
  const [payments, setPayments] = useState([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);

  useEffect(() => {
    fetchPayments();
  }, [page, search]);

  const fetchPayments = async () => {
    try {
      const res = await api.get('/payments', {
        params: { page, search }
      });
      setPayments(res.data.data);
      setLastPage(res.data.last_page);
    } catch (err) {
      console.error(err);
    }
  };

  return (
    <div>
      <table border="1" cellPadding="5" style={{ width: '100%' }}>
        <thead>
          <tr>
            <th>Payment ID</th>
            <th>TIN</th>
            <th>Taxpayer</th>
            <th>Receipt No</th>
            <th>Date</th>
            <th>Amount</th>
            <th>Mode</th>
          </tr>
        </thead>
        <tbody>
          {payments.map((p) => (
            <tr key={p.PAYMENT_ID}>
              <td>{p.PAYMENT_ID}</td>
              <td>{p.LOCAL_TIN}</td>
              <td>{p.taxpayer ? p.taxpayer.NAME : ''}</td>
              <td>{p.RECEIPTNO}</td>
              <td>{p.PAYMENTDATE}</td>
              <td>{p.AMOUNT}</td>
              <td>{p.PAYMODE_CT}</td>
            </tr>
          ))}
        </tbody>
      </table>

      <div style={{ marginTop: '10px' }}>
        {Array.from({ length: lastPage }, (_, i) => (
          <button
            key={i}
            onClick={() => setPage(i + 1)}
            style={{ margin: '0 3px', background: page === i + 1 ? '#ddd' : '#fff' }}
          >
            {i + 1}
          </button>
        ))}
      </div>
    </div>
  );

}
