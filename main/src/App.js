import React, { useEffect } from "react";
import { BrowserRouter as Router, Routes, Route, Navigate } from "react-router-dom";
import Navbar from "./components/Navbar";
import PaymentPostingPage from "./pages/PaymentPosting/PaymentPostingPage";
import ManualDebitPage from "./pages/ManualDebit/ManualDebitPage";
import PenaltyPosting from "./pages/PenaltyPosting/PenaltyPosting";
import LoginPage from "./pages/Login/LoginPage";
import "./components/Navbar.css";
import "./styles/custom-styles.css";

// Protected Route Component
const ProtectedRoute = ({ children }) => {
  const token = localStorage.getItem('auth_token');
  if (!token) {
    return <Navigate to="/login" replace />;
  }
  return children;
};

function App() {
  // 🔹 Global uppercase handler for all text inputs
  useEffect(() => {
    const handleInput = (e) => {
      const input = e.target;
      
      // Check if it's a text-based input or textarea
      const isTextInput = 
        input.tagName === 'INPUT' && 
        (input.type === 'text' || input.type === 'search' || input.type === 'email' || input.type === 'tel' || input.type === 'url');
      
      const isTextarea = input.tagName === 'TEXTAREA';
      
      if (isTextInput || isTextarea) {
        // Only convert if the value actually needs conversion
        const currentValue = input.value;
        const upperValue = currentValue.toUpperCase();
        
        if (currentValue !== upperValue) {
          const cursorPosition = input.selectionStart;
          const cursorEnd = input.selectionEnd;
          
          // Update the value using the native setter to bypass React
          const nativeInputValueSetter = Object.getOwnPropertyDescriptor(
            window.HTMLInputElement.prototype,
            'value'
          ).set;
          nativeInputValueSetter.call(input, upperValue);
          
          // Restore cursor position
          input.setSelectionRange(cursorPosition, cursorEnd);
          
          // Trigger React's onChange by dispatching a new input event
          const event = new Event('input', { bubbles: true });
          input.dispatchEvent(event);
        }
      }
    };

    // Use 'keyup' instead of 'input' to avoid conflicts
    document.addEventListener('keyup', handleInput, true);

    // Cleanup
    return () => {
      document.removeEventListener('keyup', handleInput, true);
    };
  }, []);

  const token = localStorage.getItem('auth_token');

  return (
    <Router>
      {token && <Navbar />}
      <div style={{ marginTop: token ? "0px" : "0", minHeight: "100vh" }}>
        <Routes>
          <Route path="/login" element={<LoginPage />} />
          <Route path="/" element={<ProtectedRoute><PaymentPostingPage /></ProtectedRoute>} />
          <Route path="/manual-debit" element={<ProtectedRoute><ManualDebitPage /></ProtectedRoute>} />
          <Route path="/penalty-posting" element={<ProtectedRoute><PenaltyPosting /></ProtectedRoute>} />
        </Routes>
      </div>
    </Router>
  );
}

export default App;


