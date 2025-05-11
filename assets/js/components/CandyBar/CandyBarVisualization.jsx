import React, { useState } from 'react';

const CandyBarVisualization = () => {
  // Estado para el combo seleccionado y el carrito
  const [selectedCombo, setSelectedCombo] = useState(null);
  const [cart, setCart] = useState([]);
  const [showModal, setShowModal] = useState(false);
  const [totalAmount, setTotalAmount] = useState(195.00);
  const [ticketAmount, setTicketAmount] = useState(195.00);

  // Datos de muestra para los combos
  const combos = [
    { 
      id: 1, 
      name: 'Combo DÚO', 
      price: 70.00, 
      image: '/api/placeholder/400/320',
      description: 'Pipoca grande, bebida 32oz y nachos'
    },
    { 
      id: 2, 
      name: 'Combo Película 1', 
      price: 165.00, 
      image: '/api/placeholder/400/320',
      description: 'Balde con diseño especial, bebidas 32oz'
    },
    { 
      id: 3, 
      name: 'Pipocas 2x50bs', 
      price: 50.00, 
      image: '/api/placeholder/400/320',
      description: 'Dos pipocas medianas'
    }
  ];

  // Opciones de personalización para el Combo DÚO
  const popcornOptions = [
    { id: 4, name: 'Pipoca Salada - Pipoquero Grande', image: '/api/placeholder/100/100' },
    { id: 5, name: 'Pipoca Dulce - Pipoquero Grande', image: '/api/placeholder/100/100' },
    { id: 6, name: 'Pipoca Mixta - Pipoquero Grande', image: '/api/placeholder/100/100' }
  ];

  const drinkOptions = [
    { id: 7, name: 'Vaso de 32 Oz - Coca Cola', image: '/api/placeholder/100/100' },
    { id: 8, name: 'Vaso de 32 Oz - Fanta', image: '/api/placeholder/100/100' },
    { id: 9, name: 'Vaso de 32 Oz - Sprite', image: '/api/placeholder/100/100' },
    { id: 10, name: 'Vaso de 32 Oz - Coca Cola Sin Azúcar', image: '/api/placeholder/100/100' },
    { id: 11, name: 'Vaso de 32 Oz - Fanta Papaya', image: '/api/placeholder/100/100' }
  ];

  const nachosOptions = [
    { id: 12, name: 'Bandeja Grande Nachos', image: '/api/placeholder/100/100' }
  ];

  // Estado para las cantidades seleccionadas
  const [quantities, setQuantities] = useState({
    4: 1, // Por defecto Pipoca Salada
    5: 0,
    6: 0,
    7: 1, // Por defecto Coca Cola
    8: 0,
    9: 0,
    10: 0,
    11: 0,
    12: 1  // Nachos siempre 1
  });

  // Abrir modal con el combo seleccionado
  const openCustomizeModal = (combo) => {
    setSelectedCombo(combo);
    setShowModal(true);
  };

  // Manejar cambios en la cantidad
  const handleQuantityChange = (id, delta) => {
    setQuantities(prev => {
      // Si es nacho, mantener en 1
      if (id === 12) return prev;
      
      const newQty = Math.max(0, prev[id] + delta);
      return { ...prev, [id]: newQty };
    });
  };

  // Añadir al carrito
  const addToCart = () => {
    const selectedOptions = [];
    
    // Añadir opciones seleccionadas
    [...popcornOptions, ...drinkOptions, ...nachosOptions].forEach(option => {
      if (quantities[option.id] > 0) {
        selectedOptions.push({
          ...option,
          quantity: quantities[option.id]
        });
      }
    });
    
    const cartItem = {
      ...selectedCombo,
      options: selectedOptions
    };
    
    setCart([...cart, cartItem]);
    setTotalAmount(prev => prev + selectedCombo.price);
    setShowModal(false);
  };

  return (
    <div className="flex flex-col lg:flex-row w-full bg-gray-100 min-h-screen">
      {/* Sidebar */}
      <div className="lg:w-1/4 bg-gray-900 text-white p-4">
        <div className="mb-6">
          <div className="flex items-center justify-between mb-4">
            <a href="#" className="bg-gray-800 p-2 rounded-full">
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
              </svg>
            </a>
            <div className="text-center">
              <div className="w-24 h-24 mx-auto mb-2 rounded-lg overflow-hidden">
                <img src="/api/placeholder/150/200" alt="Movie Poster" className="w-full h-full object-cover" />
              </div>
              <div className="absolute top-32 left-16 bg-yellow-500 text-black font-bold text-xs rounded-full w-6 h-6 flex items-center justify-center">12</div>
            </div>
          </div>
          <h2 className="text-xl font-bold text-center mb-1">Thunderbolts*</h2>
          <p className="text-sm text-center text-gray-400 mb-4">Multicine La Paz</p>
        </div>
        
        <div className="mb-6 border-t border-gray-800 pt-4">
          <h3 className="font-bold mb-2">Cine</h3>
          <p className="text-sm text-gray-400">Multicine La Paz</p>
          
          <h3 className="font-bold mt-4 mb-2">Fecha</h3>
          <p className="text-sm text-gray-400">viernes 09 de mayo de 2025</p>
          
          <h3 className="font-bold mt-4 mb-2">Proyección</h3>
          <p className="text-sm text-gray-400">
            14:30 3D<br />
            <span className="text-xs">Versión Original</span>
          </p>
          <p className="text-xs text-gray-500 mt-1">
            Hora prevista de finalización: 16:36
          </p>
        </div>
        
        <div className="border-t border-gray-800 pt-4">
          <h3 className="font-bold mb-3">Resumen de compra</h3>
          <div className="mb-3">
            <p className="text-sm font-bold mb-1">Asientos seleccionados:</p>
            <p className="text-sm text-yellow-500">B29, B30, B31</p>
          </div>
          
          <div className="flex justify-between text-sm mb-2">
            <span>3x Entrada</span>
            <span>Bs. 65.00</span>
          </div>
          
          {/* Items del carrito */}
          {cart.map((item, index) => (
            <div key={index} className="mb-3 pb-2 border-b border-gray-800">
              <div className="flex justify-between text-sm">
                <span>{item.name}</span>
                <span>Bs. {item.price.toFixed(2)}</span>
              </div>
              {item.options.map((option, idx) => (
                <div key={idx} className="text-xs text-gray-500 ml-3 mt-1">
                  - {option.name} x{option.quantity}
                </div>
              ))}
            </div>
          ))}
          
          <div className="flex justify-between font-bold mt-4">
            <span>Total</span>
            <span>Bs. {totalAmount.toFixed(2)}</span>
          </div>
        </div>
      </div>
      
      {/* Main content */}
      <div className="lg:w-3/4 p-6">
        <h2 className="text-2xl font-bold mb-8 text-center">Selecciona tus tarifas</h2>
        
        <div className="mb-8">
          <h3 className="text-xl font-bold mb-4">Entradas (3 asientos seleccionados)</h3>
          
          {[1, 2, 3].map((seat, index) => (
            <div key={index} className="bg-gray-50 p-4 rounded-lg mb-3 shadow-sm">
              <div className="flex flex-col">
                <span className="text-yellow-500 text-sm font-bold">Asiento B3{index + 1}</span>
                <span className="font-bold">Entrada Cine</span>
                <span className="text-gray-600">Bs. 65.00</span>
              </div>
            </div>
          ))}
        </div>
        
        <div className="bg-yellow-400 p-4 rounded-lg mb-8">
          <h3 className="text-lg font-bold mb-2">Códigos promocionales</h3>
          <p className="mb-3">Usa tus cupones online introduciendo el código impreso en tu cupón (16 caracteres).</p>
          <button className="bg-gray-900 text-white px-4 py-2 rounded-md hover:bg-gray-800 transition">
            Introducir código
          </button>
        </div>
        
        <div className="mb-8">
          <h3 className="text-xl font-bold mb-4">Candy Bar</h3>
          
          <div className="mb-4">
            <a href="#" className="bg-yellow-400 text-black px-4 py-2 rounded-md inline-block font-bold hover:bg-yellow-500 transition">
              Candy Bar(WEB)
            </a>
          </div>
          
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {combos.map(combo => (
              <div key={combo.id} className="bg-white rounded-lg overflow-hidden shadow-md transition transform hover:-translate-y-1 hover:shadow-lg">
                <div className="h-48 bg-yellow-400 flex items-center justify-center">
                  <img src={combo.image} alt={combo.name} className="max-h-40 max-w-full" />
                </div>
                <div className="p-4">
                  <h4 className="font-bold text-center">{combo.name}</h4>
                  <p className="text-center text-sm my-2">
                    Empezando desde<br />
                    Bs{combo.price.toFixed(2)}
                  </p>
                  <button 
                    onClick={() => openCustomizeModal(combo)}
                    className="w-full bg-yellow-400 text-black font-bold py-2 hover:bg-yellow-500 transition">
                    Agregar +
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>
        
        <div className="text-center">
          <button className="bg-yellow-400 text-black px-8 py-3 rounded-md font-bold hover:bg-yellow-500 transition">
            Continuar
          </button>
        </div>
      </div>
      
      {/* Modal de personalización */}
      {showModal && selectedCombo && (
        <div className="fixed inset-0 bg-black bg-opacity-75 z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-lg w-full max-w-3xl max-h-screen overflow-auto">
            <div className="bg-yellow-400 p-4 flex items-center relative">
              <button 
                onClick={() => setShowModal(false)}
                className="absolute right-4 top-4 text-2xl font-bold text-gray-600 hover:text-gray-900">
                &times;
              </button>
              <img src={selectedCombo.image} alt={selectedCombo.name} className="w-20 h-20 object-contain mr-4" />
              <div>
                <h3 className="text-xl font-bold">{selectedCombo.name}</h3>
                <p className="font-bold">Bs{selectedCombo.price.toFixed(2)}</p>
              </div>
            </div>
            
            <div className="p-4 max-h-[60vh] overflow-y-auto">
              {/* Sección de pipocas */}
              <div className="mb-6">
                <h4 className="text-lg font-bold border-b-2 border-yellow-400 pb-2 mb-3">PIPOQUERO GRANDE</h4>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                  {popcornOptions.map(option => (
                    <div key={option.id} className="border rounded-md p-3 flex items-center">
                      <img src={option.image} alt={option.name} className="w-12 h-12 object-contain mr-3" />
                      <div className="flex-1">
                        <p className="text-sm mb-2">{option.name}</p>
                        <div className="flex items-center">
                          <button 
                            onClick={() => handleQuantityChange(option.id, -1)}
                            className="w-6 h-6 rounded-full bg-yellow-400 flex items-center justify-center font-bold">
                            -
                          </button>
                          <span className="mx-3 font-bold">{quantities[option.id]}</span>
                          <button 
                            onClick={() => handleQuantityChange(option.id, 1)}
                            className="w-6 h-6 rounded-full bg-yellow-400 flex items-center justify-center font-bold">
                            +
                          </button>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
              
              {/* Sección de bebidas */}
              <div className="mb-6">
                <h4 className="text-lg font-bold border-b-2 border-yellow-400 pb-2 mb-3">VASO DE 32 Oz</h4>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                  {drinkOptions.map(option => (
                    <div key={option.id} className="border rounded-md p-3 flex items-center">
                      <img src={option.image} alt={option.name} className="w-12 h-12 object-contain mr-3" />
                      <div className="flex-1">
                        <p className="text-sm mb-2">{option.name}</p>
                        <div className="flex items-center">
                          <button 
                            onClick={() => handleQuantityChange(option.id, -1)}
                            className="w-6 h-6 rounded-full bg-yellow-400 flex items-center justify-center font-bold">
                            -
                          </button>
                          <span className="mx-3 font-bold">{quantities[option.id]}</span>
                          <button 
                            onClick={() => handleQuantityChange(option.id, 1)}
                            className="w-6 h-6 rounded-full bg-yellow-400 flex items-center justify-center font-bold">
                            +
                          </button>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
              
              {/* Sección de nachos */}
              <div>
                <h4 className="text-lg font-bold border-b-2 border-yellow-400 pb-2 mb-3">NACHOS</h4>
                <div className="grid grid-cols-1 gap-3">
                  {nachosOptions.map(option => (
                    <div key={option.id} className="border rounded-md p-3 flex items-center">
                      <img src={option.image} alt={option.name} className="w-12 h-12 object-contain mr-3" />
                      <div className="flex-1">
                        <p className="text-sm">{option.name}</p>
                        <p className="font-bold mt-1">1</p>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </div>
            
            <div className="p-4 border-t text-right">
              <button 
                onClick={addToCart}
                className="bg-yellow-500 text-black px-6 py-2 rounded-md font-bold hover:bg-yellow-600 transition">
                Añadir a la cesta
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default CandyBarVisualization;